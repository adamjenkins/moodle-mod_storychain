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

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use stdClass;

/**
 * What each role may see, as web-service payloads (also the Mustache contexts of the views).
 *
 * Students get no author ids or names and, while a chain is being written, no sentence text except the
 * one sentence before their own turn. Teachers see every chain in full but still no authors.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class state {
    /**
     * The heading of a chain: its title, or "Story N".
     *
     * @param chain_manager $manager The manager.
     * @param stdClass $chain The chain.
     * @return string Formatted (HTML-safe).
     */
    public static function heading(chain_manager $manager, stdClass $chain): string {
        $title = trim((string) $chain->title);
        if ($title === '') {
            return get_string('storyn', 'mod_storychain', (int) $chain->chainindex + 1);
        }
        return format_string($title, true, ['context' => $manager->get_context()]);
    }

    /**
     * The title a writer may see: the chain's title when the teacher shows titles, else ''.
     *
     * @param chain_manager $manager The manager.
     * @param stdClass $chain The chain.
     * @return string Formatted (HTML-safe).
     */
    protected static function writer_title(chain_manager $manager, stdClass $chain): string {
        if (!(int) $manager->get_instance()->showtitle || trim((string) $chain->title) === '') {
            return '';
        }
        return format_string($chain->title, true, ['context' => $manager->get_context()]);
    }

    /**
     * The student's view.
     *
     * @param chain_manager|null $manager The manager for the student's group, null when they have no group.
     * @param int $userid The student.
     * @param bool $canwrite Whether the student may take turns.
     * @return array
     */
    public static function student(?chain_manager $manager, int $userid, bool $canwrite): array {
        global $DB;
        $state = [
            'status' => 'nogroup',
            'canwrite' => $canwrite,
            'mysentences' => 0,
            'showprogress' => false,
            'chains' => [],
            'turn' => self::empty_turn(),
            'presentation' => self::empty_presentation(),
            'stories' => [],
        ];
        if (!$manager) {
            return $state;
        }
        $instance = $manager->get_instance();
        $now = chain_manager::now();
        $chains = $manager->get_chains();
        $state['showprogress'] = (bool) $instance->showprogress;
        if ($chains) {
            [$insql, $params] = $DB->get_in_or_equal(array_map(fn($c) => $c->id, $chains), SQL_PARAMS_NAMED);
            $params['userid'] = $userid;
            $state['mysentences'] = $DB->count_records_select(
                'storychain_sentence',
                "chainid $insql AND userid = :userid",
                $params
            );
        }

        $alldone = (bool) $chains;
        foreach ($chains as $chain) {
            $status = (int) $chain->status;
            $alldone = $alldone && $status !== chain_manager::STATUS_WRITING;
            $state['chains'][] = [
                'number' => (int) $chain->chainindex + 1,
                'title' => self::writer_title($manager, $chain),
                'sentencecount' => $instance->showprogress
                    ? $DB->count_records('storychain_sentence', ['chainid' => $chain->id]) : 0,
                'inprogress' => $status === chain_manager::STATUS_WRITING && (int) $chain->writerid > 0,
                'finished' => $status !== chain_manager::STATUS_WRITING,
            ];

            if ($status === chain_manager::STATUS_WRITING && (int) $chain->writerid === $userid) {
                $state['turn'] = self::turn($manager, $chain, $now);
            }
            if ($status === chain_manager::STATUS_REVEALED) {
                $items = $manager->story_items($chain);
                if ((int) $chain->presenting) {
                    $pos = min(count($items), (int) $chain->presentpos);
                    $shown = [];
                    for ($i = 0; $i < $pos; $i++) {
                        $shown[] = ['text' => $items[$i], 'latest' => $i === $pos - 1];
                    }
                    $state['presentation'] = [
                        'active' => true,
                        'chainid' => (int) $chain->id,
                        'heading' => self::heading($manager, $chain),
                        'position' => $pos,
                        'total' => count($items),
                        'items' => $shown,
                    ];
                }
                $state['stories'][] = [
                    'chainid' => (int) $chain->id,
                    'heading' => self::heading($manager, $chain),
                    'items' => array_map(fn($t) => ['text' => $t], $items),
                ];
            }
        }

        if ($state['turn']['active']) {
            $state['status'] = 'writing';
        } else if ($state['presentation']['active']) {
            $state['status'] = 'presenting';
        } else if ($alldone) {
            $state['status'] = 'done';
        } else {
            $state['status'] = $canwrite ? 'waiting' : 'observer';
        }
        return $state;
    }

    /**
     * The writer's turn: the previous sentence (or opening, or a blank page) and the time left.
     *
     * @param chain_manager $manager The manager.
     * @param stdClass $chain The chain whose turn the writer holds.
     * @param int $now The time.
     * @return array
     */
    protected static function turn(chain_manager $manager, stdClass $chain, int $now): array {
        $instance = $manager->get_instance();
        // The last N items a reader would see: visible sentences, and the opening when it falls inside the window.
        $items = [];
        if ($manager->has_opening($chain)) {
            $items[] = ['text' => trim($chain->opening), 'isopening' => true];
        }
        foreach ($manager->get_sentences($chain->id, true) as $sentence) {
            $items[] = ['text' => $sentence->content, 'isopening' => false];
        }
        $context = array_slice($items, -max(1, (int) $instance->contextlength));
        $previous = $context ? end($context)['text'] : '';
        $isopening = count($context) === 1 && $context[0]['isopening'];
        if ($isopening) {
            $label = get_string('openingsentence', 'mod_storychain');
        } else if (count($context) > 1) {
            $label = get_string('previoussentences', 'mod_storychain', count($context));
        } else {
            $label = get_string('previoussentence', 'mod_storychain');
        }
        return [
            'active' => true,
            'chainid' => (int) $chain->id,
            'number' => (int) $chain->chainindex + 1,
            'title' => self::writer_title($manager, $chain),
            'previous' => $previous,
            'hasprevious' => $previous !== '',
            'isopening' => $isopening,
            'context' => $context,
            'contextlabel' => $label,
            'claimstarted' => (int) $chain->claimstarted,
            'secondsleft' => max(0, (int) $chain->claimexpires - $now),
            'turntime' => (int) $instance->turntime,
            'maxlength' => (int) $instance->maxlength,
        ];
    }

    /**
     * A turn structure for "no turn".
     *
     * @return array
     */
    protected static function empty_turn(): array {
        return [
            'active' => false,
            'chainid' => 0,
            'number' => 0,
            'title' => '',
            'previous' => '',
            'hasprevious' => false,
            'isopening' => false,
            'context' => [],
            'contextlabel' => '',
            'claimstarted' => 0,
            'secondsleft' => 0,
            'turntime' => 0,
            'maxlength' => 0,
        ];
    }

    /**
     * A presentation structure for "nothing presented".
     *
     * @return array
     */
    protected static function empty_presentation(): array {
        return ['active' => false, 'chainid' => 0, 'heading' => '', 'position' => 0, 'total' => 0, 'items' => []];
    }

    /**
     * The teacher's view of every chain in the group.
     *
     * @param chain_manager $manager The manager.
     * @return array
     */
    public static function teacher(chain_manager $manager): array {
        $now = chain_manager::now();
        $labels = [
            chain_manager::STATUS_WRITING => get_string('statuswriting', 'mod_storychain'),
            chain_manager::STATUS_FINISHED => get_string('statusfinished', 'mod_storychain'),
            chain_manager::STATUS_REVEALED => get_string('statusrevealed', 'mod_storychain'),
        ];
        $chains = [];
        foreach ($manager->get_chains() as $chain) {
            $status = (int) $chain->status;
            $sentences = $manager->get_sentences($chain->id, false);
            $items = $manager->story_items($chain);
            $pos = min(count($items), (int) $chain->presentpos);
            $shownitems = [];
            foreach ($items as $i => $text) {
                $shownitems[] = ['text' => $text, 'shown' => $i < $pos, 'latest' => $i === $pos - 1];
            }
            $chains[] = [
                'id' => (int) $chain->id,
                'number' => (int) $chain->chainindex + 1,
                'heading' => self::heading($manager, $chain),
                'status' => $status,
                'statuslabel' => $labels[$status] ?? '',
                'writing' => $status === chain_manager::STATUS_WRITING,
                'finished' => $status === chain_manager::STATUS_FINISHED,
                'revealed' => $status === chain_manager::STATUS_REVEALED,
                'presenting' => (bool) $chain->presenting,
                'inprogress' => $status === chain_manager::STATUS_WRITING && (int) $chain->writerid > 0,
                'secondsleft' => (int) $chain->writerid ? max(0, (int) $chain->claimexpires - $now) : 0,
                'hasopening' => $manager->has_opening($chain),
                'opening' => $manager->has_opening($chain) ? trim($chain->opening) : '',
                'sentencecount' => count($sentences),
                'sentences' => array_map(fn($s) => [
                    'id' => (int) $s->id,
                    'seq' => (int) $s->seq,
                    'content' => $s->content,
                    'hidden' => (bool) $s->hidden,
                ], $sentences),
                'presentpos' => $pos,
                'itemcount' => count($items),
                'items' => $shownitems,
                'atstart' => $pos === 0,
                'atend' => $pos >= count($items),
            ];
        }
        return [
            'groupid' => $manager->get_groupid(),
            'online' => $manager->count_online(),
            'eligible' => count($manager->eligible_writers()),
            'chains' => $chains,
        ];
    }

    /**
     * Return structure of {@see student()}.
     *
     * @return external_single_structure
     */
    public static function student_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'writing, waiting, presenting, done, observer or nogroup'),
            'canwrite' => new external_value(PARAM_BOOL, 'Whether the user takes turns'),
            'mysentences' => new external_value(PARAM_INT, 'Sentences the user has written'),
            'showprogress' => new external_value(PARAM_BOOL, 'Whether sentence counts are shown'),
            'chains' => new external_multiple_structure(new external_single_structure([
                'number' => new external_value(PARAM_INT, 'Chain number'),
                'title' => new external_value(PARAM_RAW, 'Title, if the teacher shows titles'),
                'sentencecount' => new external_value(PARAM_INT, 'Sentences so far, if progress is shown'),
                'inprogress' => new external_value(PARAM_BOOL, 'Someone is writing'),
                'finished' => new external_value(PARAM_BOOL, 'The chain is complete'),
            ])),
            'turn' => new external_single_structure([
                'active' => new external_value(PARAM_BOOL, 'It is the user\'s turn'),
                'chainid' => new external_value(PARAM_INT, 'Chain id'),
                'number' => new external_value(PARAM_INT, 'Chain number'),
                'title' => new external_value(PARAM_RAW, 'Title, if shown to writers'),
                'previous' => new external_value(PARAM_RAW, 'The previous sentence (plain text)'),
                'hasprevious' => new external_value(PARAM_BOOL, 'There is a previous sentence'),
                'isopening' => new external_value(PARAM_BOOL, 'The previous sentence is the teacher\'s opening'),
                'context' => new external_multiple_structure(new external_single_structure([
                    'text' => new external_value(PARAM_RAW, 'Sentence (plain text)'),
                    'isopening' => new external_value(PARAM_BOOL, 'The teacher\'s opening sentence'),
                ])),
                'contextlabel' => new external_value(PARAM_TEXT, 'Caption above the sentences shown'),
                'claimstarted' => new external_value(PARAM_INT, 'When the turn started'),
                'secondsleft' => new external_value(PARAM_INT, 'Seconds left in the turn'),
                'turntime' => new external_value(PARAM_INT, 'Length of a turn in seconds'),
                'maxlength' => new external_value(PARAM_INT, 'Maximum characters'),
            ]),
            'presentation' => new external_single_structure([
                'active' => new external_value(PARAM_BOOL, 'The teacher is presenting'),
                'chainid' => new external_value(PARAM_INT, 'Chain id'),
                'heading' => new external_value(PARAM_RAW, 'Story heading'),
                'position' => new external_value(PARAM_INT, 'Items shown'),
                'total' => new external_value(PARAM_INT, 'Items in the story'),
                'items' => new external_multiple_structure(new external_single_structure([
                    'text' => new external_value(PARAM_RAW, 'Sentence (plain text)'),
                    'latest' => new external_value(PARAM_BOOL, 'The sentence just shown'),
                ])),
            ]),
            'stories' => new external_multiple_structure(new external_single_structure([
                'chainid' => new external_value(PARAM_INT, 'Chain id'),
                'heading' => new external_value(PARAM_RAW, 'Story heading'),
                'items' => new external_multiple_structure(new external_single_structure([
                    'text' => new external_value(PARAM_RAW, 'Sentence (plain text)'),
                ])),
            ])),
        ]);
    }

    /**
     * Return structure of {@see teacher()}.
     *
     * @return external_single_structure
     */
    public static function teacher_returns(): external_single_structure {
        return new external_single_structure([
            'groupid' => new external_value(PARAM_INT, 'Group shown'),
            'online' => new external_value(PARAM_INT, 'Writers online now'),
            'eligible' => new external_value(PARAM_INT, 'Writers enrolled in the group'),
            'chains' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Chain id'),
                'number' => new external_value(PARAM_INT, 'Chain number'),
                'heading' => new external_value(PARAM_RAW, 'Story heading'),
                'status' => new external_value(PARAM_INT, '0 writing, 1 finished, 2 revealed'),
                'statuslabel' => new external_value(PARAM_TEXT, 'Status label'),
                'writing' => new external_value(PARAM_BOOL, 'Being written'),
                'finished' => new external_value(PARAM_BOOL, 'Finished, not revealed'),
                'revealed' => new external_value(PARAM_BOOL, 'Revealed'),
                'presenting' => new external_value(PARAM_BOOL, 'Being presented'),
                'inprogress' => new external_value(PARAM_BOOL, 'Someone holds the turn'),
                'secondsleft' => new external_value(PARAM_INT, 'Seconds left in the current turn'),
                'hasopening' => new external_value(PARAM_BOOL, 'Has an opening sentence'),
                'opening' => new external_value(PARAM_RAW, 'Opening sentence (plain text)'),
                'sentencecount' => new external_value(PARAM_INT, 'Sentences, hidden included'),
                'sentences' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Sentence id'),
                    'seq' => new external_value(PARAM_INT, 'Order'),
                    'content' => new external_value(PARAM_RAW, 'Sentence (plain text)'),
                    'hidden' => new external_value(PARAM_BOOL, 'Hidden by the teacher'),
                ])),
                'presentpos' => new external_value(PARAM_INT, 'Items shown while presenting'),
                'itemcount' => new external_value(PARAM_INT, 'Items a reader sees'),
                'items' => new external_multiple_structure(new external_single_structure([
                    'text' => new external_value(PARAM_RAW, 'Item (plain text)'),
                    'shown' => new external_value(PARAM_BOOL, 'Shown in the presentation'),
                    'latest' => new external_value(PARAM_BOOL, 'The item just shown'),
                ])),
                'atstart' => new external_value(PARAM_BOOL, 'Nothing shown yet'),
                'atend' => new external_value(PARAM_BOOL, 'Everything shown'),
            ])),
        ]);
    }
}
