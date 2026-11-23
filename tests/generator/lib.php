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
 * Data generator for mod_storychain.
 *
 * @package    mod_storychain
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_storychain_generator extends testing_module_generator {
    #[\Override]
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) ($record ?? []) + [
            'chainmode' => 0,
            'numchains' => 1,
            'titles' => '',
            'showtitle' => 1,
            'openingmode' => 0,
            'openings' => '',
            'completionmode' => 0,
            'maxsentences' => 10,
            'deadline' => 0,
            'showprogress' => 1,
            'contextlength' => 1,
            'turntime' => 120,
            'maxlength' => 300,
        ];
        return parent::create_instance($record, (array) $options);
    }
}
