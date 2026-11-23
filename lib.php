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
 * Library of interface functions for mod_storychain.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_storychain\local\chain_manager;

/**
 * Report the features this module supports.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if the feature is supported, null if unknown
 */
function storychain_supports($feature) {
    return match ($feature) {
        FEATURE_GROUPS,
        FEATURE_GROUPINGS,
        FEATURE_MOD_INTRO,
        FEATURE_SHOW_DESCRIPTION,
        FEATURE_COMPLETION_TRACKS_VIEWS,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_GRADE_HAS_GRADE,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_COLLABORATION,
        default => null,
    };
}

/**
 * Normalise form data into an instance record.
 *
 * @param stdClass $data Form data.
 * @return stdClass
 */
function storychain_normalise_instance(stdClass $data): stdClass {
    $data->chainmode = (int) ($data->chainmode ?? 0) === chain_manager::MODE_PARALLEL
        ? chain_manager::MODE_PARALLEL : chain_manager::MODE_SINGLE;
    $data->numchains = max(1, min(chain_manager::MAX_CHAINS, (int) ($data->numchains ?? 1)));
    $data->showtitle = empty($data->showtitle) ? 0 : 1;
    $data->openingmode = (int) ($data->openingmode ?? 0) === chain_manager::OPENING_TEACHER
        ? chain_manager::OPENING_TEACHER : chain_manager::OPENING_BLANK;
    $data->completionmode = max(0, min(2, (int) ($data->completionmode ?? 0)));
    $data->maxsentences = max(1, min(500, (int) ($data->maxsentences ?? 10)));
    $data->deadline = (int) $data->completionmode === chain_manager::COMPLETE_DEADLINE ? (int) ($data->deadline ?? 0) : 0;
    $data->showprogress = empty($data->showprogress) ? 0 : 1;
    $data->contextlength = max(1, min(chain_manager::MAX_CONTEXT, (int) ($data->contextlength ?? 1)));
    $data->turntime = max(10, min(900, (int) ($data->turntime ?? 120)));
    $data->maxlength = max(20, min(2000, (int) ($data->maxlength ?? 300)));
    $data->titles = trim((string) ($data->titles ?? ''));
    $data->openings = trim((string) ($data->openings ?? ''));
    return $data;
}

/**
 * Add a story chain instance.
 *
 * @param stdClass $data Form data.
 * @param mod_storychain_mod_form|null $mform The form.
 * @return int The new instance id.
 */
function storychain_add_instance(stdClass $data, $mform = null): int {
    global $DB;
    $data = storychain_normalise_instance($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    return $DB->insert_record('storychain', $data);
}

/**
 * Update a story chain instance, and the titles and openings of its chains.
 *
 * Titles always follow the settings; an opening changes only on chains nobody has written in yet.
 *
 * @param stdClass $data Form data.
 * @param mod_storychain_mod_form|null $mform The form.
 * @return bool
 */
function storychain_update_instance(stdClass $data, $mform = null): bool {
    global $DB;
    $data = storychain_normalise_instance($data);
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('storychain', $data);

    $count = $data->chainmode === chain_manager::MODE_PARALLEL ? $data->numchains : 1;
    $titles = chain_manager::lines($data->titles, chain_manager::MAX_CHAINS);
    $openings = chain_manager::lines($data->openings, chain_manager::MAX_CHAINS);
    foreach ($DB->get_records('storychain_chain', ['storychainid' => $data->id]) as $chain) {
        $index = (int) $chain->chainindex;
        if ($index >= $count) {
            continue;
        }
        $chain->title = core_text::substr($titles[$index], 0, 255);
        if (!$DB->record_exists('storychain_sentence', ['chainid' => $chain->id])) {
            $chain->opening = $openings[$index];
        }
        $chain->timemodified = $data->timemodified;
        $DB->update_record('storychain_chain', $chain);
    }
    return true;
}

/**
 * Delete a story chain instance and all its stories.
 *
 * @param int $id The instance id.
 * @return bool
 */
function storychain_delete_instance($id): bool {
    global $DB;
    if (!$DB->record_exists('storychain', ['id' => $id])) {
        return false;
    }
    storychain_delete_stories((int) $id);
    $DB->delete_records('storychain', ['id' => $id]);
    return true;
}

/**
 * Delete every chain, sentence and participant row of an instance.
 *
 * @param int $storychainid The instance id.
 */
function storychain_delete_stories(int $storychainid): void {
    global $DB;
    $DB->delete_records_select(
        'storychain_sentence',
        'chainid IN (SELECT id FROM {storychain_chain} WHERE storychainid = ?)',
        [$storychainid]
    );
    $DB->delete_records('storychain_chain', ['storychainid' => $storychainid]);
    $DB->delete_records('storychain_participant', ['storychainid' => $storychainid]);
}

/**
 * Add the reset elements to the course reset form.
 *
 * @param MoodleQuickForm $mform The form.
 */
function storychain_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'storychainheader', get_string('modulenameplural', 'mod_storychain'));
    $mform->addElement('advcheckbox', 'reset_storychain_stories', get_string('resetstories', 'mod_storychain'));
}

/**
 * Default values of the course reset form.
 *
 * @param stdClass $course The course.
 * @return array
 */
function storychain_reset_course_form_defaults($course) {
    return ['reset_storychain_stories' => 1];
}

/**
 * Remove the stories of every story chain in a course when the course is reset.
 *
 * @param stdClass $data The reset form data.
 * @return array Status items.
 */
function storychain_reset_userdata($data) {
    global $DB;
    $status = [];
    if (!empty($data->reset_storychain_stories)) {
        foreach ($DB->get_fieldset_select('storychain', 'id', 'course = ?', [$data->courseid]) as $id) {
            storychain_delete_stories((int) $id);
        }
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_storychain'),
            'item' => get_string('resetstories', 'mod_storychain'),
            'error' => false,
        ];
    }
    if (!empty($data->timeshift)) {
        shift_course_mod_dates('storychain', ['deadline'], $data->timeshift, $data->courseid);
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_storychain'),
            'item' => get_string('deadlineshifted', 'mod_storychain'),
            'error' => false,
        ];
    }
    return $status;
}
