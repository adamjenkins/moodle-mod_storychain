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

/**
 * Restore task for mod_storychain.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/storychain/backup/moodle2/restore_storychain_stepslib.php');

/**
 * Restore task for mod_storychain.
 */
class restore_storychain_activity_task extends restore_activity_task {
    /**
     * No specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * The structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_storychain_activity_structure_step('storychain_structure', 'storychain.xml'));
    }

    /**
     * Contents with links to decode.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('storychain', ['intro'], 'storychain')];
    }

    /**
     * Link decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('STORYCHAINVIEWBYID', '/mod/storychain/view.php?id=$1', 'course_module'),
            new restore_decode_rule('STORYCHAININDEX', '/mod/storychain/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('storychain', 'view', 'view.php?id={course_module}', '{storychain}'),
        ];
    }

    /**
     * Course log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [
            new restore_log_rule('storychain', 'view all', 'index.php?id={course}', null),
        ];
    }
}
