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

/**
 * Backup and restore with user data, and course reset.
 *
 * @package    mod_storychain
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('storychain_reset_userdata')]
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_storychain_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_storychain_activity_structure_step::class)]
final class lifecycle_test extends \advanced_testcase {
    use \mod_storychain\tests\fixture_trait;

    /**
     * A course with one written sentence.
     *
     * @return array [instance, cm, chain id]
     */
    protected function written(): array {
        [$instance, $cm] = $this->make_activity(['titles' => 'Kept title', 'contextlength' => 2]);
        $ann = $this->make_student('Ann');
        $m = chain_manager::for_cm($cm, 0);
        $m->touch($ann->id);
        $m->tick();
        $id = $m->get_chains()[0]->id;
        $m->submit($ann->id, $id, 'Restored sentence.');
        return [$instance, $cm, $id];
    }

    /**
     * Backup with users, restore into a new course: settings, chain and sentence come back, no turn in progress.
     */
    public function test_backup_restore(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $cm] = $this->written();

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $this->course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $dir = 'storychain_' . time();
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $dir);

        $newcourse = $this->getDataGenerator()->create_course(['summary' => '']);
        $rc = new \restore_controller(
            $dir,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $restored = $DB->get_record('storychain', ['course' => $newcourse->id], '*', MUST_EXIST);
        $this->assertEquals(2, $restored->contextlength);
        $chain = $DB->get_record('storychain_chain', ['storychainid' => $restored->id], '*', MUST_EXIST);
        $this->assertSame('Kept title', $chain->title);
        $this->assertEquals(0, $chain->writerid);
        $this->assertSame('Restored sentence.', $DB->get_field('storychain_sentence', 'content', ['chainid' => $chain->id]));
    }

    /**
     * Course reset deletes the stories but keeps the activity.
     */
    public function test_reset(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/storychain/lib.php');
        $this->resetAfterTest();
        [$instance] = $this->written();
        $status = storychain_reset_userdata((object) ['courseid' => $this->course->id, 'reset_storychain_stories' => 1]);
        $this->assertCount(1, $status);
        $this->assertFalse($DB->record_exists('storychain_chain', ['storychainid' => $instance->id]));
        $this->assertFalse($DB->record_exists('storychain_participant', ['storychainid' => $instance->id]));
        $this->assertSame(0, $DB->count_records('storychain_sentence'));
        $this->assertTrue($DB->record_exists('storychain', ['id' => $instance->id]));
    }
}
