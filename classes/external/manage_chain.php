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
 * Close, reopen, reveal or present a chain, or move the presentation.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_chain extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'groupid' => new external_value(PARAM_INT, 'Group id (0 without groups)'),
            'chainid' => new external_value(PARAM_INT, 'Chain id'),
            'action' => new external_value(PARAM_ALPHA, 'close, reopen, reveal, present, next, prev, showall or stop'),
        ]);
    }

    /**
     * Close, reopen, reveal or present a chain, or move the presentation.
     *
     * @param int $cmid Course module id.
     * @param int $groupid Group id.
     * @param int $chainid Chain id.
     * @param string $action The action.
     * @return array
     */
    public static function execute(int $cmid, int $groupid, int $chainid, string $action): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'groupid' => $groupid,
            'chainid' => $chainid,
            'action' => $action,
        ]);
        [, $context, $manager] = access::teacher($params['cmid'], $params['groupid']);
        $manager->manage($params['chainid'], $params['action']);
        realtime::notify($context, $manager->get_groupid());
        return state::teacher($manager);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return state::teacher_returns();
    }
}
