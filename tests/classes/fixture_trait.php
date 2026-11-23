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

namespace mod_storychain\tests;

use mod_storychain\local\chain_manager;

/**
 * Shared fixtures: a course, an activity and students.
 *
 * @package    mod_storychain
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait fixture_trait {
    /** @var \stdClass The course. */
    protected $course;

    /**
     * Make a course and a story chain in it.
     *
     * @param array $settings Activity settings.
     * @param array $courseoptions Course fields.
     * @return array [instance, cm]
     */
    protected function make_activity(array $settings = [], array $courseoptions = []): array {
        $this->course = $this->getDataGenerator()->create_course(['summary' => ''] + $courseoptions);
        $instance = $this->getDataGenerator()->create_module('storychain', ['course' => $this->course->id] + $settings);
        $cm = get_coursemodule_from_instance('storychain', $instance->id, $this->course->id, false, MUST_EXIST);
        return [$instance, $cm];
    }

    /**
     * Enrol a new student.
     *
     * @param string $name First name.
     * @return \stdClass
     */
    protected function make_student(string $name): \stdClass {
        $user = $this->getDataGenerator()->create_user(['firstname' => $name]);
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        return $user;
    }

    /**
     * Enrol a new teacher.
     *
     * @return \stdClass
     */
    protected function make_teacher(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'editingteacher');
        return $user;
    }

    /**
     * A fresh manager (re-reads the activity record).
     *
     * @param \stdClass $cm The course module.
     * @param int $groupid The group.
     * @return chain_manager
     */
    protected function manager(\stdClass $cm, int $groupid = 0): chain_manager {
        return chain_manager::for_cm($cm, $groupid);
    }

    /**
     * The chain with a given number (0-based), re-read from the DB.
     *
     * @param chain_manager $manager The manager.
     * @param int $index The chain index.
     * @return \stdClass
     */
    protected function chain(chain_manager $manager, int $index = 0): \stdClass {
        return $manager->get_chains()[$index];
    }
}
