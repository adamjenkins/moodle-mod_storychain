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

namespace mod_storychain\external;

use core_external\external_api;

/**
 * The web services: capability and group gates, and the shapes they return.
 *
 * @package    mod_storychain
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_student_state::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(submit_sentence::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(pass_turn::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_teacher_state::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(manage_chain::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_sentence_hidden::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_storychain\local\access::class)]
final class services_test extends \advanced_testcase {
    use \mod_storychain\tests\fixture_trait;

    /**
     * A student polls, gets the turn, writes, and the teacher sees the sentence; returns validate.
     */
    public function test_student_and_teacher_flow(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1900000000);
        [, $cm] = $this->make_activity();
        $ann = $this->make_student('Ann');
        $teacher = $this->make_teacher();

        $this->setUser($ann);
        $state = external_api::clean_returnvalue(
            get_student_state::execute_returns(),
            get_student_state::execute($cm->id)
        );
        $this->assertSame('writing', $state['status']);
        $state = external_api::clean_returnvalue(
            submit_sentence::execute_returns(),
            submit_sentence::execute($cm->id, $state['turn']['chainid'], 'Hello there.')
        );
        // Ann is alone and just wrote, so she gets the next turn straight away.
        $this->assertSame('writing', $state['status']);
        $this->assertSame('Hello there.', $state['turn']['previous']);
        $state = external_api::clean_returnvalue(
            pass_turn::execute_returns(),
            pass_turn::execute($cm->id, $state['turn']['chainid'])
        );

        $this->setUser($teacher);
        $tstate = external_api::clean_returnvalue(
            get_teacher_state::execute_returns(),
            get_teacher_state::execute($cm->id, 0)
        );
        $chain = $tstate['chains'][0];
        $this->assertSame('Hello there.', $chain['sentences'][0]['content']);
        $tstate = external_api::clean_returnvalue(
            set_sentence_hidden::execute_returns(),
            set_sentence_hidden::execute($cm->id, 0, $chain['sentences'][0]['id'], true)
        );
        $this->assertTrue($tstate['chains'][0]['sentences'][0]['hidden']);
        $tstate = external_api::clean_returnvalue(
            manage_chain::execute_returns(),
            manage_chain::execute($cm->id, 0, $chain['id'], 'close')
        );
        $this->assertTrue($tstate['chains'][0]['finished']);
    }

    /**
     * Students cannot call the teacher services.
     */
    public function test_student_cannot_manage(): void {
        $this->resetAfterTest();
        [, $cm] = $this->make_activity();
        $this->setUser($this->make_student('Ann'));
        $this->expectException(\required_capability_exception::class);
        get_teacher_state::execute($cm->id, 0);
    }

    /**
     * A non-editing teacher in separate groups cannot open another group's chains.
     */
    public function test_teacher_limited_to_own_groups(): void {
        global $DB;
        $this->resetAfterTest();
        [, $cm] = $this->make_activity(['groupmode' => SEPARATEGROUPS]);
        $g1 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $g2 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $g1->id, 'userid' => $teacher->id]);
        $role = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $role, \context_course::instance($this->course->id), true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($teacher);
        $state = get_teacher_state::execute($cm->id, $g1->id);
        $this->assertEquals($g1->id, $state['groupid']);
        try {
            get_teacher_state::execute($cm->id, $g2->id);
            $this->fail('Expected invalidgroup');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidgroup', $e->errorcode);
        }
    }

    /**
     * A student in no group (groups on) gets the "no group" screen and cannot write.
     */
    public function test_student_without_group(): void {
        $this->resetAfterTest();
        [, $cm] = $this->make_activity(['groupmode' => SEPARATEGROUPS]);
        $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->setUser($this->make_student('Ann'));
        $this->assertSame('nogroup', get_student_state::execute($cm->id)['status']);
        try {
            submit_sentence::execute($cm->id, 1, 'Hi.');
            $this->fail('Expected nogroup');
        } catch (\moodle_exception $e) {
            $this->assertSame('nogroup', $e->errorcode);
        }
    }
}
