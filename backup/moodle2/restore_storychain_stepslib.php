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
 * Restore structure for mod_storychain.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore structure for mod_storychain.
 *
 * Turns in progress and presentations are not restored: a restored chain starts with no writer.
 */
class restore_storychain_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the paths.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [new restore_path_element('storychain', '/activity/storychain')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('storychain_chain', '/activity/storychain/chains/chain');
            $paths[] = new restore_path_element('storychain_sentence', '/activity/storychain/chains/chain/sentences/sentence');
            $paths[] = new restore_path_element('storychain_participant', '/activity/storychain/participants/participant');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity record.
     *
     * @param array $data The data.
     */
    protected function process_storychain($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->deadline = $data->deadline ? $this->apply_date_offset($data->deadline) : 0;
        $newid = $DB->insert_record('storychain', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * The new group id for an old one: 0 stays 0, an unmapped group gives null.
     *
     * @param int $oldgroupid The old group id.
     * @return int|null
     */
    protected function map_group(int $oldgroupid): ?int {
        if ($oldgroupid === 0) {
            return 0;
        }
        $new = $this->get_mappingid('group', $oldgroupid);
        return $new ? (int) $new : null;
    }

    /**
     * Restore a chain.
     *
     * @param array $data The data.
     */
    protected function process_storychain_chain($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $groupid = $this->map_group((int) $data->groupid);
        if ($groupid === null) {
            return;
        }
        $data->storychainid = $this->get_new_parentid('storychain');
        $data->groupid = $groupid;
        if (
            $DB->record_exists('storychain_chain', [
            'storychainid' => $data->storychainid,
            'groupid' => $data->groupid,
            'chainindex' => $data->chainindex,
            ])
        ) {
            return;
        }
        $data->writerid = 0;
        $data->claimstarted = 0;
        $data->claimexpires = 0;
        $data->presenting = 0;
        $newid = $DB->insert_record('storychain_chain', $data);
        $this->set_mapping('storychain_chain', $oldid, $newid);
    }

    /**
     * Restore a sentence.
     *
     * @param array $data The data.
     */
    protected function process_storychain_sentence($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $chainid = $this->get_new_parentid('storychain_chain');
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$chainid || !$userid) {
            return;
        }
        $data->chainid = $chainid;
        $data->userid = $userid;
        $newid = $DB->insert_record('storychain_sentence', $data);
        $this->set_mapping('storychain_sentence', $oldid, $newid);
    }

    /**
     * Restore a participant row.
     *
     * @param array $data The data.
     */
    protected function process_storychain_participant($data) {
        global $DB;
        $data = (object) $data;
        $groupid = $this->map_group((int) $data->groupid);
        $userid = $this->get_mappingid('user', $data->userid);
        if ($groupid === null || !$userid) {
            return;
        }
        $data->storychainid = $this->get_new_parentid('storychain');
        $data->groupid = $groupid;
        $data->userid = $userid;
        if (
            $DB->record_exists('storychain_participant', [
            'storychainid' => $data->storychainid,
            'groupid' => $data->groupid,
            'userid' => $data->userid,
            ])
        ) {
            return;
        }
        $DB->insert_record('storychain_participant', $data);
    }

    /**
     * Restore the intro files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_storychain', 'intro', null);
    }
}
