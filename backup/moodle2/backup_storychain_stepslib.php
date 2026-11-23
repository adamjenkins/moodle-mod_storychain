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
 * Backup structure for mod_storychain.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup structure for mod_storychain: settings, and with user data the chains, sentences and turn counts.
 */
class backup_storychain_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $storychain = new backup_nested_element('storychain', ['id'], [
            'name', 'intro', 'introformat', 'chainmode', 'numchains', 'titles', 'showtitle', 'openingmode', 'openings',
            'completionmode', 'maxsentences', 'deadline', 'showprogress', 'contextlength', 'turntime', 'maxlength',
            'timecreated', 'timemodified',
        ]);
        $chains = new backup_nested_element('chains');
        $chain = new backup_nested_element('chain', ['id'], [
            'groupid', 'chainindex', 'title', 'opening', 'status', 'presentpos', 'timecreated', 'timemodified',
        ]);
        $sentences = new backup_nested_element('sentences');
        $sentence = new backup_nested_element('sentence', ['id'], ['userid', 'seq', 'content', 'hidden', 'timecreated']);
        $participants = new backup_nested_element('participants');
        $participant = new backup_nested_element('participant', ['id'], [
            'groupid', 'userid', 'turns', 'lastseen', 'waitingsince', 'timecreated',
        ]);

        $storychain->add_child($chains);
        $chains->add_child($chain);
        $chain->add_child($sentences);
        $sentences->add_child($sentence);
        $storychain->add_child($participants);
        $participants->add_child($participant);

        $storychain->set_source_table('storychain', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $chain->set_source_table('storychain_chain', ['storychainid' => backup::VAR_PARENTID], 'id ASC');
            $sentence->set_source_table('storychain_sentence', ['chainid' => backup::VAR_PARENTID], 'id ASC');
            $participant->set_source_table('storychain_participant', ['storychainid' => backup::VAR_PARENTID], 'id ASC');
        }

        $chain->annotate_ids('group', 'groupid');
        $sentence->annotate_ids('user', 'userid');
        $participant->annotate_ids('group', 'groupid');
        $participant->annotate_ids('user', 'userid');

        $storychain->annotate_files('mod_storychain', 'intro', null);

        return $this->prepare_activity_structure($storychain);
    }
}
