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

namespace mod_storychain\local;

use context_module;
use core_external\external_api;
use moodle_exception;

/**
 * Access checks shared by the web services: the course module, its context and the right group.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Validate a web-service call from a student's page.
     *
     * @param int $cmid The course module.
     * @param string $capability The capability required.
     * @return array [cm_info, context_module, chain_manager|null] (null when groups are on and the user has none)
     */
    public static function student(int $cmid, string $capability): array {
        global $USER;
        [$cm, $context] = self::module($cmid);
        require_capability($capability, $context);
        $groupid = chain_manager::student_groupid($cm, (int) $USER->id);
        $manager = $groupid === null ? null : chain_manager::for_cm($cm, $groupid);
        return [$cm, $context, $manager];
    }

    /**
     * Validate a web-service call from a teacher's page.
     *
     * @param int $cmid The course module.
     * @param int $groupid The group asked for.
     * @return array [cm_info, context_module, chain_manager]
     * @throws moodle_exception When the teacher may see no group.
     */
    public static function teacher(int $cmid, int $groupid): array {
        global $USER;
        [$cm, $context] = self::module($cmid);
        require_capability('mod/storychain:manage', $context);
        $resolved = chain_manager::teacher_groupid($cm, $groupid, (int) $USER->id);
        if ($resolved === null || (groups_get_activity_groupmode($cm) != NOGROUPS && $resolved !== $groupid)) {
            throw new moodle_exception('invalidgroup', 'mod_storychain');
        }
        return [$cm, $context, chain_manager::for_cm($cm, $resolved)];
    }

    /**
     * The course module and its validated context.
     *
     * @param int $cmid The course module.
     * @return array [cm_info, context_module]
     */
    protected static function module(int $cmid): array {
        [, $cm] = get_course_and_cm_from_cmid($cmid, 'storychain');
        $context = context_module::instance($cm->id);
        external_api::validate_context($context);
        return [$cm, $context];
    }
}
