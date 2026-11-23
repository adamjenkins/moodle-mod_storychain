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
 * The story chain: the writer's screen for students, the live view for teachers.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_storychain\local\chain_manager;
use mod_storychain\local\realtime;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'storychain');
$instance = $DB->get_record('storychain', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/storychain:view', $context);

\mod_storychain\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context' => $context,
])->trigger();
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$url = new moodle_url('/mod/storychain/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

$manage = has_capability('mod/storychain:manage', $context);
$realtime = realtime::is_available();
$jsconfig = [
    'interval' => max(1, (int) (get_config('mod_storychain', 'pollinterval') ?: 3)) * 1000,
    'realtime' => $realtime,
];

if ($manage) {
    $requested = (int) groups_get_activity_group($cm, true);
    $groupid = chain_manager::teacher_groupid($cm, $requested, (int) $USER->id);
    if ($groupid !== null) {
        realtime::subscribe($context, $groupid);
        $jsconfig['groupid'] = $groupid;
        $jsconfig['presenturl'] = (new moodle_url('/mod/storychain/present.php', [
            'id' => $cm->id,
            'group' => $groupid,
        ]))->out(false);
        $PAGE->requires->js_call_amd('mod_storychain/teacher', 'init', [$cm->id, $jsconfig]);
    }
    echo $OUTPUT->header();
    groups_print_activity_menu($cm, $url);
    if (!$realtime) {
        echo $OUTPUT->notification(get_string(
            realtime::is_installed() ? 'realtimenotenabled' : 'realtimemissing',
            'mod_storychain'
        ), \core\output\notification::NOTIFY_INFO);
    }
    if ($groupid === null) {
        echo $OUTPUT->notification(get_string('nogroupsavailable', 'mod_storychain'), \core\output\notification::NOTIFY_WARNING);
    } else {
        echo $OUTPUT->render_from_template('mod_storychain/teacher', [
            'cmid' => $cm->id,
            'groupid' => $groupid,
        ]);
    }
} else {
    $groupid = chain_manager::student_groupid($cm, (int) $USER->id);
    if ($groupid !== null) {
        realtime::subscribe($context, $groupid);
    }
    $jsconfig['groupid'] = (int) $groupid;
    $PAGE->requires->js_call_amd('mod_storychain/student', 'init', [$cm->id, $jsconfig]);
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_storychain/student', ['cmid' => $cm->id]);
}

echo $OUTPUT->footer();
