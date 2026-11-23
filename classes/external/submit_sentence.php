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
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_storychain\local\access;
use mod_storychain\local\realtime;
use mod_storychain\local\state;

/**
 * Add the writer's sentence to the chain whose turn they hold.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_sentence extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'chainid' => new external_value(PARAM_INT, 'Chain id'),
            'text' => new external_value(PARAM_RAW, 'The sentence (plain text)'),
        ]);
    }

    /**
     * Add the writer's sentence to the chain whose turn they hold.
     *
     * @param int $cmid Course module id.
     * @param int $chainid Chain id.
     * @param string $text The sentence.
     * @return array
     */
    public static function execute(int $cmid, int $chainid, string $text): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'chainid' => $chainid,
            'text' => $text,
        ]);
        [, $context, $manager] = access::student($params['cmid'], 'mod/storychain:write');
        if (!$manager) {
            throw new \moodle_exception('nogroup', 'mod_storychain');
        }
        $manager->touch((int) $USER->id);
        $manager->submit((int) $USER->id, $params['chainid'], $params['text']);
        realtime::notify($context, $manager->get_groupid());
        return state::student($manager, (int) $USER->id, true);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return state::student_returns();
    }
}
