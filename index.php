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
 * List of story chains in a course.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

\mod_storychain\event\course_module_instance_list_viewed::create([
    'context' => context_course::instance($course->id),
])->trigger();

$PAGE->set_url('/mod/storychain/index.php', ['id' => $course->id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'mod_storychain'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_storychain'));

$table = new html_table();
$table->head = [get_string('name'), get_string('moduleintro')];
foreach (get_all_instances_in_course('storychain', $course) as $instance) {
    if (!$instance->visible && !has_capability('moodle/course:viewhiddenactivities', context_course::instance($course->id))) {
        continue;
    }
    $link = html_writer::link(
        new moodle_url('/mod/storychain/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name),
        $instance->visible ? [] : ['class' => 'dimmed']
    );
    $intro = format_module_intro('storychain', $instance, $instance->coursemodule);
    $table->data[] = [$link, $intro];
}

if ($table->data) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nostorychains', 'mod_storychain'), \core\output\notification::NOTIFY_INFO);
}
echo $OUTPUT->footer();
