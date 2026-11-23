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

namespace mod_storychain\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_storychain\local\chain_manager;

/**
 * Privacy provider: metadata, export and deletion.
 *
 * @package    mod_storychain
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    use \mod_storychain\tests\fixture_trait;

    /**
     * Two students who each wrote a sentence.
     *
     * @return array [cm, context, ann, ben, manager]
     */
    protected function setup_story(): array {
        $this->mock_clock_with_frozen(1900000000);
        [, $cm] = $this->make_activity();
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = chain_manager::for_cm($cm, 0);
        $m->touch($ann->id);
        $m->touch($ben->id);
        $m->tick();
        $id = $m->get_chains()[0]->id;
        $m->submit($ann->id, $id, 'Ann wrote this.');
        $m->submit($ben->id, $id, 'Ben wrote this.');
        return [$cm, \context_module::instance($cm->id), $ann, $ben, $m];
    }

    /**
     * Every table is described.
     */
    public function test_metadata(): void {
        $items = provider::get_metadata(new collection('mod_storychain'))->get_collection();
        $this->assertCount(3, $items);
    }

    /**
     * Contexts, users and export.
     */
    public function test_contexts_users_and_export(): void {
        $this->resetAfterTest();
        [, $context, $ann, $ben] = $this->setup_story();
        $this->assertEquals([$context->id], provider::get_contexts_for_userid($ann->id)->get_contextids());
        $userlist = new userlist($context, 'mod_storychain');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([(int) $ann->id, (int) $ben->id], array_map('intval', $userlist->get_userids()));

        provider::export_user_data(new approved_contextlist($ann, 'mod_storychain', [$context->id]));
        $data = writer::with_context($context)->get_data([]);
        $this->assertCount(1, $data->sentences);
        $this->assertSame('Ann wrote this.', $data->sentences[0]['sentence']);
        $this->assertSame(1, $data->turns[0]['turns']);
    }

    /**
     * Deleting one user leaves the other's sentence; deleting the context leaves nothing.
     */
    public function test_delete(): void {
        global $DB;
        $this->resetAfterTest();
        [, $context, $ann, $ben] = $this->setup_story();
        provider::delete_data_for_user(new approved_contextlist($ann, 'mod_storychain', [$context->id]));
        $this->assertFalse($DB->record_exists('storychain_sentence', ['userid' => $ann->id]));
        $this->assertFalse($DB->record_exists('storychain_participant', ['userid' => $ann->id]));
        $this->assertFalse($DB->record_exists('storychain_chain', ['writerid' => $ann->id]));
        $this->assertTrue($DB->record_exists('storychain_sentence', ['userid' => $ben->id]));

        provider::delete_data_for_users(new approved_userlist($context, 'mod_storychain', [$ben->id]));
        $this->assertFalse($DB->record_exists('storychain_sentence', ['userid' => $ben->id]));

        [, $context2, $cat] = $this->setup_story();
        provider::delete_data_for_all_users_in_context($context2);
        $this->assertFalse($DB->record_exists('storychain_sentence', ['userid' => $cat->id]));
        $this->assertFalse($DB->record_exists('storychain_participant', ['userid' => $cat->id]));
    }
}
