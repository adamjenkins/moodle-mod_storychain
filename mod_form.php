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
 * Settings form for mod_storychain.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_storychain\local\chain_manager;

/**
 * Settings form for mod_storychain.
 */
class mod_storychain_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'chainshdr', get_string('chainssettings', 'mod_storychain'));
        $mform->setExpanded('chainshdr');

        $mform->addElement('select', 'chainmode', get_string('chainmode', 'mod_storychain'), [
            chain_manager::MODE_SINGLE => get_string('chainmodesingle', 'mod_storychain'),
            chain_manager::MODE_PARALLEL => get_string('chainmodeparallel', 'mod_storychain'),
        ]);
        $mform->addHelpButton('chainmode', 'chainmode', 'mod_storychain');

        $counts = [];
        for ($i = 2; $i <= chain_manager::MAX_CHAINS; $i++) {
            $counts[$i] = $i;
        }
        $mform->addElement('select', 'numchains', get_string('numchains', 'mod_storychain'), $counts);
        $mform->setDefault('numchains', 3);
        $mform->addHelpButton('numchains', 'numchains', 'mod_storychain');
        $mform->hideIf('numchains', 'chainmode', 'neq', chain_manager::MODE_PARALLEL);

        $mform->addElement('textarea', 'titles', get_string('titles', 'mod_storychain'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('titles', PARAM_TEXT);
        $mform->addHelpButton('titles', 'titles', 'mod_storychain');

        $mform->addElement('advcheckbox', 'showtitle', get_string('showtitle', 'mod_storychain'));
        $mform->setDefault('showtitle', 1);
        $mform->addHelpButton('showtitle', 'showtitle', 'mod_storychain');

        $contexts = [];
        for ($i = 1; $i <= chain_manager::MAX_CONTEXT; $i++) {
            $contexts[$i] = $i;
        }
        $mform->addElement('select', 'contextlength', get_string('contextlength', 'mod_storychain'), $contexts);
        $mform->setDefault('contextlength', 1);
        $mform->addHelpButton('contextlength', 'contextlength', 'mod_storychain');

        $mform->addElement('select', 'openingmode', get_string('openingmode', 'mod_storychain'), [
            chain_manager::OPENING_BLANK => get_string('openingblank', 'mod_storychain'),
            chain_manager::OPENING_TEACHER => get_string('openingteacher', 'mod_storychain'),
        ]);
        $mform->addHelpButton('openingmode', 'openingmode', 'mod_storychain');

        $mform->addElement('textarea', 'openings', get_string('openings', 'mod_storychain'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('openings', PARAM_TEXT);
        $mform->addHelpButton('openings', 'openings', 'mod_storychain');
        $mform->hideIf('openings', 'openingmode', 'neq', chain_manager::OPENING_TEACHER);

        $mform->addElement('header', 'turnshdr', get_string('turnssettings', 'mod_storychain'));
        $mform->setExpanded('turnshdr');

        $mform->addElement('select', 'completionmode', get_string('completionmode', 'mod_storychain'), [
            chain_manager::COMPLETE_SENTENCES => get_string('completionsentences', 'mod_storychain'),
            chain_manager::COMPLETE_DEADLINE => get_string('completiondeadline', 'mod_storychain'),
            chain_manager::COMPLETE_EVERYONE => get_string('completioneveryone', 'mod_storychain'),
        ]);
        $mform->addHelpButton('completionmode', 'completionmode', 'mod_storychain');

        $mform->addElement('text', 'maxsentences', get_string('maxsentences', 'mod_storychain'), ['size' => 4]);
        $mform->setType('maxsentences', PARAM_INT);
        $mform->setDefault('maxsentences', 10);
        $mform->hideIf('maxsentences', 'completionmode', 'neq', chain_manager::COMPLETE_SENTENCES);

        $mform->addElement('date_time_selector', 'deadline', get_string('deadline', 'mod_storychain'));
        $mform->setDefault('deadline', time() + HOURSECS);
        $mform->hideIf('deadline', 'completionmode', 'neq', chain_manager::COMPLETE_DEADLINE);

        $mform->addElement('select', 'turntime', get_string('turntime', 'mod_storychain'), [
            30 => get_string('numseconds', 'moodle', 30),
            60 => get_string('numminutes', 'moodle', 1),
            90 => get_string('numseconds', 'moodle', 90),
            120 => get_string('numminutes', 'moodle', 2),
            180 => get_string('numminutes', 'moodle', 3),
            300 => get_string('numminutes', 'moodle', 5),
            600 => get_string('numminutes', 'moodle', 10),
        ]);
        $mform->setDefault('turntime', 120);
        $mform->addHelpButton('turntime', 'turntime', 'mod_storychain');

        $mform->addElement('text', 'maxlength', get_string('maxlength', 'mod_storychain'), ['size' => 5]);
        $mform->setType('maxlength', PARAM_INT);
        $mform->setDefault('maxlength', 300);

        $mform->addElement('advcheckbox', 'showprogress', get_string('showprogress', 'mod_storychain'));
        $mform->setDefault('showprogress', 1);
        $mform->addHelpButton('showprogress', 'showprogress', 'mod_storychain');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validate the settings.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $completionmode = (int) ($data['completionmode'] ?? 0);
        if ($completionmode === chain_manager::COMPLETE_SENTENCES) {
            $max = (int) ($data['maxsentences'] ?? 0);
            if ($max < 1 || $max > 500) {
                $errors['maxsentences'] = get_string('maxsentencesinvalid', 'mod_storychain');
            }
        }
        if ($completionmode === chain_manager::COMPLETE_DEADLINE && empty($data['deadline'])) {
            $errors['deadline'] = get_string('required');
        }
        $maxlength = (int) ($data['maxlength'] ?? 0);
        if ($maxlength < 20 || $maxlength > 2000) {
            $errors['maxlength'] = get_string('maxlengthinvalid', 'mod_storychain');
        }
        return $errors;
    }
}
