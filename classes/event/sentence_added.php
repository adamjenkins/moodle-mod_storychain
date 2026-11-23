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

namespace mod_storychain\event;

/**
 * A writer added a sentence to a story.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int chainid: the chain the sentence was added to.
 * }
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sentence_added extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'storychain_sentence';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsentenceadded', 'mod_storychain');
    }

    /**
     * Description (not shown to students; the stories stay anonymous to them).
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' added the sentence with id '$this->objectid' to the story chain " .
            "with course module id '$this->contextinstanceid'.";
    }

    /**
     * URL of the activity.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/storychain/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Validate the data.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['chainid'])) {
            throw new \coding_exception('The \'chainid\' value must be set in other.');
        }
    }

    /**
     * Mapping of objectid for restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'storychain_sentence', 'restore' => 'storychain_sentence'];
    }

    /**
     * Mapping of other for restore.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return ['chainid' => ['db' => 'storychain_chain', 'restore' => 'storychain_chain']];
    }
}
