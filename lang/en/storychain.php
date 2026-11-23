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
 * English strings for mod_storychain.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addsentence'] = 'Add my sentence';
$string['allfinished'] = 'All the stories are finished. Your teacher will reveal them soon.';
$string['backtoliveview'] = 'Back to the live view';
$string['blankpage'] = 'You are starting this story. Write its first sentence.';
$string['busy'] = 'The story is busy. Please try again.';
$string['chainmode'] = 'Mode';
$string['chainmode_help'] = 'With a single chain, each group (or the whole class) writes one story, taking turns. With parallel chains, several stories are written at the same time and students rotate between them, so fewer of them sit waiting.';
$string['chainmodeparallel'] = 'Parallel chains';
$string['chainmodesingle'] = 'Single chain';
$string['chainssettings'] = 'Stories';
$string['charsleft'] = '{$a} characters left';
$string['closechain'] = 'Close';
$string['completiondeadline'] = 'At a deadline';
$string['completioneveryone'] = 'When every student has had a turn';
$string['completionmode'] = 'A story is finished';
$string['completionmode_help'] = 'After a fixed number of sentences; at a deadline (all stories still being written are finished then); or when every student in the group has written a sentence in it. You can also close a story yourself at any time.';
$string['completionsentences'] = 'After a number of sentences';
$string['contextlength'] = 'How many sentences the writer sees';
$string['contextlength_help'] = 'How many of the story\'s latest sentences the next writer can read before writing theirs. 1, the default, is the classic game: only the sentence just before. More sentences give more context, like an AI that remembers further back; the rest of the story stays hidden until the reveal. The opening sentence counts as one when it falls inside this window.';
$string['deadline'] = 'Deadline';
$string['deadlineshifted'] = 'Story chain deadlines shifted';
$string['emptysentence'] = 'Write a sentence first.';
$string['eventsentenceadded'] = 'Sentence added';
$string['hiddenbyteacher'] = 'Hidden';
$string['hidesentence'] = 'Hide sentence {$a}';
$string['invalidaction'] = 'Unknown action.';
$string['invalidchain'] = 'This story does not exist.';
$string['invalidgroup'] = 'You cannot see this group\'s stories.';
$string['invalidsentence'] = 'This sentence does not exist.';
$string['loading'] = 'Loading the stories...';
$string['maxlength'] = 'Maximum characters per sentence';
$string['maxlengthinvalid'] = 'Enter a number from 20 to 2000.';
$string['maxsentences'] = 'Number of sentences';
$string['maxsentencesinvalid'] = 'Enter a number from 1 to 500.';
$string['modulename'] = 'Story chain';
$string['modulename_help'] = 'In a story chain, students write a story together, one sentence each, in the style of the "exquisite corpse" game. Each writer sees only the sentence just before theirs, so nobody knows the whole story until the teacher reveals it, usually on a projector.

Turns are handed out automatically to students who have the activity open. Stories are anonymous.';
$string['modulenameplural'] = 'Story chains';
$string['mysentences'] = 'You have written {$a} sentence(s) so far.';
$string['nextsentence'] = 'Next sentence';
$string['nogroup'] = 'You are not in a group for this activity, so you cannot join a story yet. Ask your teacher.';
$string['nogroupsavailable'] = 'There are no groups you can see in this activity.';
$string['nosentences'] = 'No sentences yet.';
$string['nostorychains'] = 'There are no story chains in this course.';
$string['notyourturn'] = 'It is not your turn any more. Your sentence was not added.';
$string['numchains'] = 'Number of chains';
$string['numchains_help'] = 'How many stories each group writes at the same time. For example, 3 chains suit a group of 5.';
$string['observer'] = 'You can read the stories here once your teacher reveals them, but you cannot write in them.';
$string['onlinecount'] = '{$a->online} of {$a->eligible} writers have the activity open.';
$string['openingblank'] = 'The first student starts from a blank page';
$string['openingmode'] = 'Opening sentence';
$string['openingmode_help'] = 'Either you write each story\'s opening sentence, or the first student starts from a blank page.';
$string['openings'] = 'Opening sentences';
$string['openings_help'] = 'One opening sentence per line: line 1 opens story 1, line 2 opens story 2, and so on. A story whose line is empty starts from a blank page.';
$string['openingsentence'] = 'The opening sentence:';
$string['openingteacher'] = 'I write the opening sentence';
$string['otherstories'] = 'Other stories';
$string['passturn'] = 'Pass my turn';
$string['pluginadministration'] = 'Story chain administration';
$string['pluginname'] = 'Story chain';
$string['pollinterval'] = 'Polling interval (seconds)';
$string['pollinterval_desc'] = 'Without the real-time plugin, open pages ask the server for changes this often. Shorter is livelier but costs more requests.';
$string['present'] = 'Present';
$string['presentcontrols'] = 'Presentation controls';
$string['presentingnow'] = 'Being presented';
$string['presentkeys'] = 'Keys: right arrow or space for the next sentence, left arrow to go back.';
$string['presentprogress'] = '{$a->position} of {$a->total}';
$string['presentstart'] = 'Press "Next sentence" to begin.';
$string['previoussentence'] = 'The sentence before yours:';
$string['previoussentencebutton'] = 'Previous sentence';
$string['previoussentences'] = 'The last {$a} sentences before yours:';
$string['privacy:metadata:storychain_chain'] = 'The story chains, including who holds the current turn.';
$string['privacy:metadata:storychain_chain:claimstarted'] = 'When the current writer\'s turn started.';
$string['privacy:metadata:storychain_chain:writerid'] = 'The user who holds the current turn.';
$string['privacy:metadata:storychain_participant'] = 'Each writer\'s presence and turn count, used to hand out turns fairly.';
$string['privacy:metadata:storychain_participant:lastseen'] = 'When the writer last had the activity open.';
$string['privacy:metadata:storychain_participant:turns'] = 'How many turns the writer has had (sentences written plus turns passed or let run out).';
$string['privacy:metadata:storychain_participant:userid'] = 'The writer.';
$string['privacy:metadata:storychain_participant:waitingsince'] = 'Since when the writer has been waiting for a turn.';
$string['privacy:metadata:storychain_sentence'] = 'The sentences students write.';
$string['privacy:metadata:storychain_sentence:content'] = 'The sentence.';
$string['privacy:metadata:storychain_sentence:hidden'] = 'Whether the teacher hid the sentence.';
$string['privacy:metadata:storychain_sentence:timecreated'] = 'When the sentence was written.';
$string['privacy:metadata:storychain_sentence:userid'] = 'The writer of the sentence.';
$string['realtimeinfo'] = 'Story chain works best with the real-time plugin (tool_realtime by Marina Glancy), which hands turns over and moves students\' screens instantly. Without it, open pages poll the server instead: everything still works, a few seconds more slowly.';
$string['realtimemissing'] = 'Instant updates are off because the real-time plugin (tool_realtime) is not installed, so screens update every few seconds instead. Ask your site administrator to install it for faster turn handover.';
$string['realtimenotenabled'] = 'Instant updates are off because the real-time plugin (tool_realtime) has no backend enabled, so screens update every few seconds instead.';
$string['reopenchain'] = 'Reopen';
$string['resetstories'] = 'Delete all stories';
$string['reveal'] = 'Reveal to students';
$string['revealedstories'] = 'The stories';
$string['secondsleft'] = '{$a} s left';
$string['sentenceadded'] = 'Your sentence was added. Thank you!';
$string['sentencecount'] = '{$a} sentence(s)';
$string['sentencetoolong'] = 'Your sentence is too long: the most is {$a} characters.';
$string['showall'] = 'Show all';
$string['showprogress'] = 'Show progress to waiting students';
$string['showprogress_help'] = 'If ticked, waiting students see how many sentences each story has. They never see the sentences themselves.';
$string['showsentence'] = 'Show sentence {$a}';
$string['showtitle'] = 'Show the title to writers';
$string['showtitle_help'] = 'If ticked, the writer sees the story\'s title next to the previous sentence, as a little context.';
$string['someoneiswriting'] = 'Someone is writing';
$string['statusfinished'] = 'Finished';
$string['statusrevealed'] = 'Revealed';
$string['statuswriting'] = 'Being written';
$string['stoppresenting'] = 'Stop presenting';
$string['storychain:addinstance'] = 'Add a new story chain';
$string['storychain:manage'] = 'Run a story chain: see every story, close, reveal and present them, hide sentences';
$string['storychain:view'] = 'View a story chain';
$string['storychain:write'] = 'Take turns writing in a story chain';
$string['storyn'] = 'Story {$a}';
$string['storytitle'] = 'Story:';
$string['teacherisshowing'] = 'Your teacher is showing this story';
$string['timeleft'] = 'Time left: {$a}';
$string['timeup'] = 'Time is up. Send your sentence now or it will pass to someone else.';
$string['titles'] = 'Story titles';
$string['titles_help'] = 'One title per line: line 1 is the title of story 1, line 2 of story 2, and so on. A story without a title is called "Story 1", "Story 2" and so on.';
$string['turnpassed'] = 'You passed your turn.';
$string['turnssettings'] = 'Turns';
$string['turntime'] = 'Time per turn';
$string['turntime_help'] = 'How long a student has to write their sentence. When the time runs out, or the student closes the page, the turn passes to someone else.';
$string['waitingexplain'] = 'Turns go to whoever has written least so far. Keep this page open: your turn will come.';
$string['waitingforturn'] = 'Waiting for your turn';
$string['waitingforwriter'] = 'Waiting for a writer to come online.';
$string['yoursentence'] = 'Your sentence';
$string['yourturn'] = 'It\'s your turn!';
$string['yourturntitle'] = 'Your turn! {$a}';
