<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_storychain;

use mod_storychain\local\chain_manager;
use mod_storychain\local\state;

/**
 * Turn allocation, sentences, completion and the reveal.
 *
 * @package    mod_storychain
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_storychain\local\chain_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_storychain\local\state::class)]
final class chain_manager_test extends \advanced_testcase {
    use \mod_storychain\tests\fixture_trait;

    /** @var int Frozen start time. */
    const T = 1900000000;

    /**
     * Set a participant's turn count directly.
     *
     * @param chain_manager $manager The manager.
     * @param int $userid The user.
     * @param int $turns Turns.
     */
    protected function set_turns(chain_manager $manager, int $userid, int $turns): void {
        global $DB;
        $DB->set_field('storychain_participant', 'turns', $turns, [
            'storychainid' => $manager->get_instance()->id,
            'userid' => $userid,
        ]);
    }

    /**
     * The next turn goes to the online writer with the fewest turns; ties go to whoever waited longest.
     */
    public function test_turn_goes_to_fewest_turns_then_longest_wait(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity();
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $cat = $this->make_student('Cat');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $clock->bump(1);
        $m->touch($ben->id);
        $clock->bump(1);
        $m->touch($cat->id);
        $this->set_turns($m, $ann->id, 2);

        $this->assertTrue($m->tick());
        // Ben and Cat both have 0 turns; Ben has waited longer.
        $this->assertEquals($ben->id, $this->chain($m)->writerid);
        $this->assertEquals(self::T + 2 + 120, $this->chain($m)->claimexpires);
        // Nothing more to hand out.
        $this->assertFalse($m->tick());
    }

    /**
     * Writers who have not sent a heartbeat for 45 seconds get no turn.
     */
    public function test_offline_writers_get_no_turn(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity();
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $clock->bump(chain_manager::ONLINE_WINDOW + 1);
        $m->touch($ben->id);
        $this->set_turns($m, $ben->id, 5);

        $m->tick();
        $this->assertEquals($ben->id, $this->chain($m)->writerid);
    }

    /**
     * A turn that runs out passes on (after the grace period), and counts as a turn for its holder.
     */
    public function test_expired_turn_passes_on(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity(['turntime' => 60]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->tick();
        $this->assertEquals($ann->id, $this->chain($m)->writerid);
        $clock->bump(1);
        $m->touch($ben->id);

        // Still Ann's within the turn and its grace period.
        $clock->bump(60 + chain_manager::SUBMIT_GRACE - 1);
        $m->touch($ann->id);
        $m->touch($ben->id);
        $this->assertFalse($m->tick());
        $this->assertEquals($ann->id, $this->chain($m)->writerid);

        $clock->bump(2);
        $this->assertTrue($m->tick());
        $this->assertEquals($ben->id, $this->chain($m)->writerid);
        $this->assertEquals(1, $DB->get_field('storychain_participant', 'turns', ['userid' => $ann->id]));
    }

    /**
     * A writer who closes the page loses the turn once they count as offline.
     */
    public function test_offline_writer_loses_turn(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity(['turntime' => 600]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->tick();
        $clock->bump(chain_manager::ONLINE_WINDOW + 1);
        $m->touch($ben->id);
        $m->tick();
        $this->assertEquals($ben->id, $this->chain($m)->writerid);
    }

    /**
     * A sentence is cleaned, stored and the turn handed on; the next writer sees only that sentence.
     */
    public function test_submit_hands_over_and_next_writer_sees_only_previous(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity(['titles' => "The lost key\nOther"]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $clock->bump(1);
        $m->touch($ben->id);
        $m->tick();
        $chain = $this->chain($m);
        $this->assertEquals($ann->id, $chain->writerid);

        $sentence = $m->submit($ann->id, $chain->id, "  It was   a <b>dark</b>\n night. ");
        $this->assertSame('It was a dark night.', $sentence->content);
        $this->assertEquals($ben->id, $this->chain($m)->writerid);

        $m->submit($ben->id, $chain->id, 'A key fell.');
        $this->assertEquals($ann->id, $this->chain($m)->writerid);

        $annstate = state::student($m, $ann->id, true);
        $this->assertSame('writing', $annstate['status']);
        $this->assertSame('A key fell.', $annstate['turn']['previous']);
        $this->assertSame('The lost key', $annstate['turn']['title']);
        $this->assertStringNotContainsString('dark night', json_encode($annstate));

        $benstate = state::student($m, $ben->id, true);
        $this->assertSame('waiting', $benstate['status']);
        $this->assertTrue($benstate['chains'][0]['inprogress']);
        $this->assertSame(2, $benstate['chains'][0]['sentencecount']);
        $this->assertSame(1, $benstate['mysentences']);
        $this->assertStringNotContainsString('key fell', json_encode($benstate));
        $this->assertStringNotContainsString('dark night', json_encode($benstate));
    }

    /**
     * Titles are hidden from writers when the teacher turns them off; the opening shows before any sentence.
     */
    public function test_opening_and_hidden_title(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity([
            'titles' => 'Secret title',
            'showtitle' => 0,
            'openingmode' => chain_manager::OPENING_TEACHER,
            'openings' => 'Once upon a time.',
        ]);
        $ann = $this->make_student('Ann');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->tick();
        $state = state::student($m, $ann->id, true);
        $this->assertSame('Once upon a time.', $state['turn']['previous']);
        $this->assertTrue($state['turn']['isopening']);
        $this->assertSame('', $state['turn']['title']);
        $this->assertStringNotContainsString('Secret title', json_encode($state));
    }

    /**
     * With a context length of 2 the writer sees the last two items (opening included), never earlier ones.
     */
    public function test_context_length_shows_last_sentences(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity([
            'contextlength' => 2,
            'openingmode' => chain_manager::OPENING_TEACHER,
            'openings' => 'Once upon a time.',
        ]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->touch($ben->id);
        $m->tick();
        $id = $this->chain($m)->id;

        $turn = state::student($m, $ann->id, true)['turn'];
        $this->assertSame(['Once upon a time.'], array_column($turn['context'], 'text'));
        $this->assertTrue($turn['isopening']);

        $m->submit($ann->id, $id, 'One.');
        $turn = state::student($m, $ben->id, true)['turn'];
        $this->assertSame(['Once upon a time.', 'One.'], array_column($turn['context'], 'text'));
        $this->assertSame(get_string('previoussentences', 'mod_storychain', 2), $turn['contextlabel']);

        $m->submit($ben->id, $id, 'Two.');
        $m->submit($ann->id, $id, 'Three.');
        $state = state::student($m, $ben->id, true);
        $this->assertSame(['Two.', 'Three.'], array_column($state['turn']['context'], 'text'));
        $this->assertStringNotContainsString('One.', json_encode($state));
        $this->assertStringNotContainsString('Once upon', json_encode($state));
    }

    /**
     * Submissions are refused when empty, too long or out of turn, each with its own error.
     */
    public function test_submit_refusals(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity(['maxlength' => 20]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->touch($ben->id);
        $m->tick();
        $chain = $this->chain($m);
        $this->assertEquals($ann->id, $chain->writerid);

        foreach (
            [
            [$ann->id, ' <p> </p> ', 'emptysentence'],
            [$ann->id, str_repeat('x', 21), 'sentencetoolong'],
            [$ben->id, 'Not mine.', 'notyourturn'],
            ] as [$userid, $text, $code]
        ) {
            try {
                $m->submit($userid, $chain->id, $text);
                $this->fail("Expected $code");
            } catch (\moodle_exception $e) {
                $this->assertSame($code, $e->errorcode);
            }
        }
        $this->assertSame([], $m->get_sentences($chain->id, false));
    }

    /**
     * Passing counts as a turn and hands the chain on.
     */
    public function test_pass_counts_as_turn(): void {
        global $DB;
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity();
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->touch($ben->id);
        $m->tick();
        $m->pass($ann->id, $this->chain($m)->id);
        $this->assertEquals($ben->id, $this->chain($m)->writerid);
        $this->assertEquals(1, $DB->get_field('storychain_participant', 'turns', ['userid' => $ann->id]));
    }

    /**
     * A chain finishes after its number of sentences, and finished chains hand out no turns.
     */
    public function test_fixed_sentence_completion(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity(['maxsentences' => 2]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->touch($ben->id);
        $m->tick();
        $id = $this->chain($m)->id;
        $m->submit($ann->id, $id, 'One.');
        $m->submit($ben->id, $id, 'Two.');
        $chain = $this->chain($m);
        $this->assertEquals(chain_manager::STATUS_FINISHED, $chain->status);
        $this->assertEquals(0, $chain->writerid);
        $this->assertFalse($m->tick());
        $this->assertSame('done', state::student($m, $ann->id, true)['status']);
    }

    /**
     * "Everyone has had a turn": nobody writes twice in a chain, and it finishes once all enrolled writers wrote.
     */
    public function test_everyone_completion(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity(['completionmode' => chain_manager::COMPLETE_EVERYONE]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->tick();
        $id = $this->chain($m)->id;
        $m->submit($ann->id, $id, 'One.');
        // Ann is the only one online but has written here already.
        $this->assertEquals(0, $this->chain($m)->writerid);
        $this->assertEquals(chain_manager::STATUS_WRITING, $this->chain($m)->status);

        $m->touch($ben->id);
        $m->tick();
        $this->assertEquals($ben->id, $this->chain($m)->writerid);
        $m->submit($ben->id, $id, 'Two.');
        $this->assertEquals(chain_manager::STATUS_FINISHED, $this->chain($m)->status);
    }

    /**
     * At the deadline every chain still being written finishes, turn holders included.
     */
    public function test_deadline_completion(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity([
            'completionmode' => chain_manager::COMPLETE_DEADLINE,
            'deadline' => self::T + 60,
            'chainmode' => chain_manager::MODE_PARALLEL,
            'numchains' => 2,
        ]);
        $ann = $this->make_student('Ann');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->tick();
        $clock->bump(59);
        $m->touch($ann->id);
        $m->tick();
        $this->assertEquals(chain_manager::STATUS_WRITING, $this->chain($m, 0)->status);
        $clock->bump(1);
        $this->assertTrue($m->tick());
        foreach ($m->get_chains() as $chain) {
            $this->assertEquals(chain_manager::STATUS_FINISHED, $chain->status);
            $this->assertEquals(0, $chain->writerid);
        }
    }

    /**
     * Parallel chains: one turn per writer at a time; a chain waits when nobody is free.
     */
    public function test_parallel_one_turn_per_writer(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity(['chainmode' => chain_manager::MODE_PARALLEL, 'numchains' => 3]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->touch($ben->id);
        $m->tick();
        $writers = array_map(fn($c) => (int) $c->writerid, array_values($m->get_chains()));
        $this->assertCount(3, $writers);
        $this->assertEqualsCanonicalizing([(int) $ann->id, (int) $ben->id, 0], $writers);
    }

    /**
     * A writer who just wrote a chain's last sentence does not get that chain straight back on a tie.
     */
    public function test_rotation_prefers_someone_else_for_the_next_sentence(): void {
        global $DB;
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity();
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->tick();
        $m->submit($ann->id, $this->chain($m)->id, 'One.');
        // Ann wrote last; Ben arrives with the same turn count and a later wait.
        $m->touch($ben->id);
        $this->set_turns($m, $ben->id, 1);
        $DB->set_field('storychain_chain', 'writerid', 0, ['id' => $this->chain($m)->id]);
        $m->tick();
        $this->assertEquals($ben->id, $this->chain($m)->writerid);
    }

    /**
     * Presenting reveals the chain and steps through opening and visible sentences; students follow.
     */
    public function test_present_steps_and_students_follow(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity([
            'openingmode' => chain_manager::OPENING_TEACHER,
            'openings' => 'Once upon a time.',
        ]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->touch($ben->id);
        $m->tick();
        $id = $this->chain($m)->id;
        $one = $m->submit($ann->id, $id, 'One.');
        $m->submit($ben->id, $id, 'Two.');
        $m->set_hidden($one->id, true);
        $m->manage($id, 'close');
        $this->assertSame('done', state::student($m, $ann->id, true)['status']);
        $this->assertSame([], state::student($m, $ann->id, true)['stories']);

        $chain = $m->manage($id, 'present');
        $this->assertEquals(chain_manager::STATUS_REVEALED, $chain->status);
        $this->assertEquals(0, $chain->presentpos);
        $m->manage($id, 'next');
        $m->manage($id, 'next');
        $chain = $m->manage($id, 'next');
        // Opening + "Two." (the hidden sentence is skipped).
        $this->assertEquals(2, $chain->presentpos);

        $state = state::student($m, $ben->id, true);
        $this->assertSame('presenting', $state['status']);
        $this->assertSame(['Once upon a time.', 'Two.'], array_column($state['presentation']['items'], 'text'));
        $this->assertTrue($state['presentation']['items'][1]['latest']);
        $this->assertStringNotContainsString('One.', json_encode($state));

        $m->manage($id, 'prev');
        $this->assertCount(1, state::student($m, $ben->id, true)['presentation']['items']);
        $m->manage($id, 'stop');
        $state = state::student($m, $ben->id, true);
        $this->assertSame('done', $state['status']);
        $this->assertSame(['Once upon a time.', 'Two.'], array_column($state['stories'][0]['items'], 'text'));

        $teacher = state::teacher($m);
        $this->assertCount(2, $teacher['chains'][0]['sentences']);
        $this->assertTrue($teacher['chains'][0]['sentences'][0]['hidden']);
        $this->assertStringNotContainsString((string) $ann->id . '"', json_encode(array_column($teacher['chains'], 'sentences')));
    }

    /**
     * With separate groups each group has its own chain and writers.
     */
    public function test_groups_have_their_own_chains(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [, $cm] = $this->make_activity(['groupmode' => SEPARATEGROUPS]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $cat = $this->make_student('Cat');
        $g1 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $g2 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $g1->id, 'userid' => $ann->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $g2->id, 'userid' => $ben->id]);

        $this->assertEquals($g1->id, chain_manager::student_groupid($cm, $ann->id));
        $this->assertEquals($g2->id, chain_manager::student_groupid($cm, $ben->id));
        $this->assertNull(chain_manager::student_groupid($cm, $cat->id));

        $m1 = $this->manager($cm, $g1->id);
        $m2 = $this->manager($cm, $g2->id);
        $m1->touch($ann->id);
        $m2->touch($ben->id);
        $m1->tick();
        $m2->tick();
        $this->assertEquals($ann->id, $this->chain($m1)->writerid);
        $this->assertEquals($ben->id, $this->chain($m2)->writerid);
        $this->assertNotEquals($this->chain($m1)->id, $this->chain($m2)->id);
        $this->expectException(\moodle_exception::class);
        $m2->get_chain($this->chain($m1)->id);
    }

    /**
     * Editing the settings updates titles everywhere and openings only where nobody has written yet.
     */
    public function test_update_instance_syncs_titles_and_unwritten_openings(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/storychain/lib.php');
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T);
        [$instance, $cm] = $this->make_activity([
            'chainmode' => chain_manager::MODE_PARALLEL,
            'numchains' => 2,
            'openingmode' => chain_manager::OPENING_TEACHER,
            'openings' => "Old one.\nOld two.",
        ]);
        $ann = $this->make_student('Ann');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->tick();
        $written = array_values(array_filter($m->get_chains(), fn($c) => (int) $c->writerid))[0];
        $m->submit($ann->id, $written->id, 'Started.');

        $data = (object) (array) $instance;
        $data->instance = $instance->id;
        $data->titles = "First\nSecond";
        $data->openings = "New one.\nNew two.";
        storychain_update_instance($data);

        $m = $this->manager($cm);
        foreach ($m->get_chains() as $index => $chain) {
            $this->assertSame($index ? 'Second' : 'First', $chain->title);
            $expected = (int) $chain->id === (int) $written->id
                ? ($index ? 'Old two.' : 'Old one.')
                : ($index ? 'New two.' : 'New one.');
            $this->assertSame($expected, $chain->opening);
        }
    }
}
