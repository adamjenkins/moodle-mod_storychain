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
 * External functions for the story chain browser clients.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_storychain_get_student_state' => [
        'classname' => \mod_storychain\external\get_student_state::class,
        'description' => 'Mark the writer as online, hand out free turns and return what this writer may see.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/storychain:view',
    ],
    'mod_storychain_submit_sentence' => [
        'classname' => \mod_storychain\external\submit_sentence::class,
        'description' => 'Add the writer\'s sentence to the chain whose turn they hold.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/storychain:write',
    ],
    'mod_storychain_pass_turn' => [
        'classname' => \mod_storychain\external\pass_turn::class,
        'description' => 'Give up the turn the writer holds, so it passes to someone else.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/storychain:write',
    ],
    'mod_storychain_get_teacher_state' => [
        'classname' => \mod_storychain\external\get_teacher_state::class,
        'description' => 'Return every chain of a group in full, for the teacher\'s live view and presenter.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/storychain:manage',
    ],
    'mod_storychain_manage_chain' => [
        'classname' => \mod_storychain\external\manage_chain::class,
        'description' => 'Close, reopen, reveal or present a chain, or move the presentation.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/storychain:manage',
    ],
    'mod_storychain_set_sentence_hidden' => [
        'classname' => \mod_storychain\external\set_sentence_hidden::class,
        'description' => 'Hide or show one sentence of a story.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/storychain:manage',
    ],
];
