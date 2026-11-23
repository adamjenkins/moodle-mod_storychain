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
 * The teacher's projector view: step through one story; students' screens follow.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_storychain\local\chain_manager;
use mod_storychain\local\realtime;
use mod_storychain\local\state;

$id = required_param('id', PARAM_INT);
$requested = optional_param('group', 0, PARAM_INT);
$chainid = required_param('chainid', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'storychain');
$instance = $DB->get_record('storychain', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/storychain:manage', $context);

$groupid = chain_manager::teacher_groupid($cm, $requested, (int) $USER->id);
if ($groupid === null || (groups_get_activity_groupmode($cm) != NOGROUPS && $groupid !== $requested)) {
    throw new moodle_exception('invalidgroup', 'mod_storychain');
}
$manager = new chain_manager($instance, $cm, $groupid);
$chain = $manager->get_chain($chainid);

$url = new moodle_url('/mod/storychain/present.php', ['id' => $cm->id, 'group' => $groupid, 'chainid' => $chain->id]);
$viewurl = new moodle_url('/mod/storychain/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(format_string($instance->name) . ': ' . html_entity_decode(state::heading($manager, $chain), ENT_QUOTES));
$PAGE->add_body_class('mod-storychain-presenting');

realtime::subscribe($context, $groupid);

$others = [];
foreach ($manager->get_chains() as $other) {
    if ((int) $other->status === chain_manager::STATUS_WRITING) {
        continue;
    }
    $others[] = [
        'heading' => state::heading($manager, $other),
        'url' => (new moodle_url('/mod/storychain/present.php', [
            'id' => $cm->id,
            'group' => $groupid,
            'chainid' => $other->id,
        ]))->out(false),
        'current' => (int) $other->id === (int) $chain->id,
    ];
}

$PAGE->requires->js_call_amd('mod_storychain/present', 'init', [$cm->id, [
    'groupid' => $groupid,
    'chainid' => (int) $chain->id,
    'interval' => max(1, (int) (get_config('mod_storychain', 'pollinterval') ?: 3)) * 1000,
    'realtime' => realtime::is_available(),
    'viewurl' => $viewurl->out(false),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_storychain/present', [
    'cmid' => $cm->id,
    'name' => format_string($instance->name),
    'viewurl' => $viewurl->out(false),
    'others' => $others,
    'hasothers' => count($others) > 1,
]);
echo $OUTPUT->footer();
