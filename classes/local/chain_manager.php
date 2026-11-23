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
use core_text;
use moodle_exception;
use stdClass;

/**
 * The chains of one story chain activity for one group: turns, sentences, completion and the reveal.
 *
 * Turns are handed out lazily: every state request calls {@see tick()}, which releases claims that ran out
 * or whose writer went offline and gives each free chain to the best online writer. All writes for one
 * activity and group happen under one lock, so two requests can never hand out the same turn.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chain_manager {
    /** @var int Chain is being written. */
    const STATUS_WRITING = 0;
    /** @var int Chain is complete but not yet shown to students. */
    const STATUS_FINISHED = 1;
    /** @var int Chain has been revealed to students. */
    const STATUS_REVEALED = 2;

    /** @var int One chain per group. */
    const MODE_SINGLE = 0;
    /** @var int Several chains per group at once. */
    const MODE_PARALLEL = 1;

    /** @var int The first writer starts from a blank page. */
    const OPENING_BLANK = 0;
    /** @var int The teacher writes the opening sentence. */
    const OPENING_TEACHER = 1;

    /** @var int A chain finishes after a fixed number of sentences. */
    const COMPLETE_SENTENCES = 0;
    /** @var int All chains finish at a deadline. */
    const COMPLETE_DEADLINE = 1;
    /** @var int A chain finishes when every writer in the group has written in it. */
    const COMPLETE_EVERYONE = 2;

    /** @var int Highest number of parallel chains. */
    const MAX_CHAINS = 10;
    /** @var int Most sentences a writer may be shown before their turn. */
    const MAX_CONTEXT = 10;
    /** @var int Seconds since the last heartbeat after which a writer counts as offline. */
    const ONLINE_WINDOW = 45;
    /** @var int Seconds after a turn runs out in which the writer's submission is still accepted. */
    const SUBMIT_GRACE = 15;

    /** @var stdClass The activity record. */
    protected stdClass $instance;
    /** @var stdClass|\cm_info The course module. */
    protected $cm;
    /** @var context_module The module context. */
    protected context_module $context;
    /** @var int The group whose chains this manager handles (0 = no groups). */
    protected int $groupid;

    /**
     * Constructor.
     *
     * @param stdClass $instance The storychain record.
     * @param stdClass|\cm_info $cm The course module.
     * @param int $groupid The group (0 when the activity has no groups).
     */
    public function __construct(stdClass $instance, $cm, int $groupid) {
        $this->instance = $instance;
        $this->cm = $cm;
        $this->context = context_module::instance($cm->id);
        $this->groupid = $groupid;
    }

    /**
     * Build a manager for a course module and group.
     *
     * @param stdClass|\cm_info $cm The course module.
     * @param int $groupid The group.
     * @return self
     */
    public static function for_cm($cm, int $groupid): self {
        global $DB;
        $instance = $DB->get_record('storychain', ['id' => $cm->instance], '*', MUST_EXIST);
        return new self($instance, $cm, $groupid);
    }

    /**
     * The current time, from core's clock (so tests can freeze it).
     *
     * @return int
     */
    public static function now(): int {
        return \core\di::get(\core\clock::class)->time();
    }

    /**
     * The activity record.
     *
     * @return stdClass
     */
    public function get_instance(): stdClass {
        return $this->instance;
    }

    /**
     * The module context.
     *
     * @return context_module
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * The group handled.
     *
     * @return int
     */
    public function get_groupid(): int {
        return $this->groupid;
    }

    /**
     * The group a writer belongs to for this activity.
     *
     * @param stdClass|\cm_info $cm The course module.
     * @param int $userid The writer.
     * @return int|null The group id, 0 without groups, null when groups are on and the user is in none.
     */
    public static function student_groupid($cm, int $userid): ?int {
        if (groups_get_activity_groupmode($cm) == NOGROUPS) {
            return 0;
        }
        $groups = groups_get_all_groups($cm->course, $userid, $cm->groupingid, 'g.id');
        if (!$groups) {
            return null;
        }
        return (int) min(array_keys($groups));
    }

    /**
     * The group a teacher views: the one asked for if allowed, else the first allowed one.
     *
     * @param stdClass|\cm_info $cm The course module.
     * @param int $requested The group asked for.
     * @param int $userid The teacher.
     * @return int|null The group id, 0 without groups, null when no group is allowed.
     */
    public static function teacher_groupid($cm, int $requested, int $userid): ?int {
        if (groups_get_activity_groupmode($cm) == NOGROUPS) {
            return 0;
        }
        $allowed = groups_get_activity_allowed_groups($cm, $userid);
        if (isset($allowed[$requested])) {
            return $requested;
        }
        if (!$allowed) {
            return null;
        }
        return (int) min(array_keys($allowed));
    }

    /**
     * How many chains each group has.
     *
     * @return int
     */
    public function num_chains(): int {
        if ((int) $this->instance->chainmode !== self::MODE_PARALLEL) {
            return 1;
        }
        return max(1, min(self::MAX_CHAINS, (int) $this->instance->numchains));
    }

    /**
     * Split a one-line-per-chain setting into trimmed lines.
     *
     * @param string|null $text The setting.
     * @param int $count How many lines to return.
     * @return string[] Exactly $count lines, padded with ''.
     */
    public static function lines(?string $text, int $count): array {
        $lines = preg_split('/\R/u', (string) $text);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = trim($lines[$i] ?? '');
        }
        return $out;
    }

    /**
     * Whether a chain shows the teacher's opening sentence.
     *
     * @param stdClass $chain The chain.
     * @return bool
     */
    public function has_opening(stdClass $chain): bool {
        return (int) $this->instance->openingmode === self::OPENING_TEACHER && trim((string) $chain->opening) !== '';
    }

    /**
     * The chains of this group, by chain number, creating any that do not exist yet.
     *
     * @return stdClass[] Keyed by chain index.
     */
    public function ensure_chains(): array {
        global $DB;
        $chains = $this->get_chains();
        $count = $this->num_chains();
        if (count($chains) >= $count) {
            return $chains;
        }
        $titles = self::lines($this->instance->titles, $count);
        $openings = self::lines($this->instance->openings, $count);
        $now = self::now();
        for ($i = 0; $i < $count; $i++) {
            if (isset($chains[$i])) {
                continue;
            }
            $chain = (object) [
                'storychainid' => $this->instance->id,
                'groupid' => $this->groupid,
                'chainindex' => $i,
                'title' => core_text::substr($titles[$i], 0, 255),
                'opening' => $openings[$i],
                'status' => self::STATUS_WRITING,
                'writerid' => 0,
                'claimstarted' => 0,
                'claimexpires' => 0,
                'presenting' => 0,
                'presentpos' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $chain->id = $DB->insert_record('storychain_chain', $chain);
            $chains[$i] = $chain;
        }
        ksort($chains);
        return $chains;
    }

    /**
     * The existing chains of this group, by chain number.
     *
     * @return stdClass[] Keyed by chain index.
     */
    public function get_chains(): array {
        global $DB;
        $records = $DB->get_records_select(
            'storychain_chain',
            'storychainid = :sid AND groupid = :gid AND chainindex < :count',
            ['sid' => $this->instance->id, 'gid' => $this->groupid, 'count' => $this->num_chains()],
            'chainindex ASC'
        );
        $chains = [];
        foreach ($records as $record) {
            $chains[(int) $record->chainindex] = $record;
        }
        return $chains;
    }

    /**
     * One chain of this group.
     *
     * @param int $chainid The chain id.
     * @return stdClass
     * @throws moodle_exception When the chain belongs to another activity or group.
     */
    public function get_chain(int $chainid): stdClass {
        global $DB;
        $chain = $DB->get_record('storychain_chain', [
            'id' => $chainid,
            'storychainid' => $this->instance->id,
            'groupid' => $this->groupid,
        ]);
        if (!$chain || (int) $chain->chainindex >= $this->num_chains()) {
            throw new moodle_exception('invalidchain', 'mod_storychain');
        }
        return $chain;
    }

    /**
     * The sentences of a chain in order.
     *
     * @param int $chainid The chain.
     * @param bool $visibleonly Leave out hidden sentences.
     * @return stdClass[]
     */
    public function get_sentences(int $chainid, bool $visibleonly): array {
        global $DB;
        $params = ['chainid' => $chainid];
        if ($visibleonly) {
            $params['hidden'] = 0;
        }
        return array_values($DB->get_records('storychain_sentence', $params, 'seq ASC, id ASC'));
    }

    /**
     * The texts a reader sees, in order: the opening (if any), then the visible sentences.
     *
     * @param stdClass $chain The chain.
     * @return string[]
     */
    public function story_items(stdClass $chain): array {
        $items = [];
        if ($this->has_opening($chain)) {
            $items[] = trim($chain->opening);
        }
        foreach ($this->get_sentences($chain->id, true) as $sentence) {
            $items[] = $sentence->content;
        }
        return $items;
    }

    /**
     * Mark a writer as online now, creating their participant row on first contact.
     *
     * @param int $userid The writer.
     * @return stdClass The participant row.
     */
    public function touch(int $userid): stdClass {
        global $DB;
        $now = self::now();
        $conditions = ['storychainid' => $this->instance->id, 'groupid' => $this->groupid, 'userid' => $userid];
        $participant = $DB->get_record('storychain_participant', $conditions);
        if ($participant) {
            $DB->set_field('storychain_participant', 'lastseen', $now, ['id' => $participant->id]);
            $participant->lastseen = $now;
            return $participant;
        }
        $participant = (object) ($conditions + [
            'turns' => 0,
            'lastseen' => $now,
            'waitingsince' => $now,
            'timecreated' => $now,
        ]);
        try {
            $participant->id = $DB->insert_record('storychain_participant', $participant);
        } catch (\dml_write_exception $e) {
            // Two tabs of the same user raced; the other one inserted the row.
            $participant = $DB->get_record('storychain_participant', $conditions, '*', MUST_EXIST);
        }
        return $participant;
    }

    /**
     * Release stale turns, finish completed chains and hand out free turns.
     *
     * @return bool Whether anything changed that other screens should know about.
     */
    public function tick(): bool {
        $changed = $this->with_lock(fn() => $this->do_tick(), false);
        return (bool) $changed;
    }

    /**
     * The body of {@see tick()}; the caller holds the lock.
     *
     * @return bool Whether anything changed.
     */
    protected function do_tick(): bool {
        global $DB;
        $now = self::now();
        $changed = false;
        $before = count($this->get_chains());
        $chains = $this->ensure_chains();
        $changed = $changed || count($chains) !== $before;

        $deadlinepassed = (int) $this->instance->completionmode === self::COMPLETE_DEADLINE
            && (int) $this->instance->deadline > 0 && $now >= (int) $this->instance->deadline;

        $participants = [];
        foreach (
            $DB->get_records('storychain_participant', [
            'storychainid' => $this->instance->id,
            'groupid' => $this->groupid,
            ]) as $record
        ) {
            $participants[(int) $record->userid] = $record;
        }

        // Finish chains that are complete and release turns that ran out or whose writer left.
        foreach ($chains as $chain) {
            if ((int) $chain->status !== self::STATUS_WRITING) {
                continue;
            }
            if ($deadlinepassed || ((int) $chain->writerid === 0 && $this->is_complete($chain))) {
                $this->finish($chain);
                $changed = true;
                continue;
            }
            if ((int) $chain->writerid === 0) {
                continue;
            }
            $writer = $participants[(int) $chain->writerid] ?? null;
            $offline = !$writer || (int) $writer->lastseen < $now - self::ONLINE_WINDOW;
            if ($offline || (int) $chain->claimexpires + self::SUBMIT_GRACE < $now) {
                $this->release($chain, $writer, true);
                if ($writer) {
                    $writer->turns = (int) $writer->turns + 1;
                    $writer->waitingsince = $now;
                }
                $changed = true;
            }
        }

        // Online writers who hold no turn.
        $busy = [];
        foreach ($chains as $chain) {
            if ((int) $chain->writerid) {
                $busy[(int) $chain->writerid] = true;
            }
        }
        $candidates = [];
        foreach ($participants as $userid => $participant) {
            if (isset($busy[$userid]) || (int) $participant->lastseen < $now - self::ONLINE_WINDOW) {
                continue;
            }
            if (!has_capability('mod/storychain:write', $this->context, $userid)) {
                continue;
            }
            $candidates[$userid] = $participant;
        }

        // Free chains, the one waiting longest first.
        $free = array_filter($chains, fn($c) => (int) $c->status === self::STATUS_WRITING && !(int) $c->writerid);
        usort($free, fn($a, $b) => [(int) $a->timemodified, (int) $a->chainindex]
            <=> [(int) $b->timemodified, (int) $b->chainindex]);

        foreach ($free as $chain) {
            if (!$candidates) {
                break;
            }
            $userid = $this->choose_writer($chain, $candidates);
            if ($userid === null) {
                continue;
            }
            $chain->writerid = $userid;
            $chain->claimstarted = $now;
            $chain->claimexpires = $now + max(10, (int) $this->instance->turntime);
            $chain->timemodified = $now;
            $DB->update_record('storychain_chain', $chain);
            unset($candidates[$userid]);
            $changed = true;
        }
        return $changed;
    }

    /**
     * Pick the next writer for a chain.
     *
     * Fewest turns first. Ties go to whoever did not write this chain's last sentence, then to whoever has
     * written least in this chain, then to whoever has waited longest.
     *
     * @param stdClass $chain The free chain.
     * @param stdClass[] $candidates Online writers without a turn, keyed by user id.
     * @return int|null The chosen user id, or null when nobody may write in this chain.
     */
    protected function choose_writer(stdClass $chain, array $candidates): ?int {
        global $DB;
        $counts = $DB->get_records_sql_menu(
            'SELECT userid, COUNT(1) FROM {storychain_sentence} WHERE chainid = :chainid GROUP BY userid',
            ['chainid' => $chain->id]
        );
        $last = $DB->get_records('storychain_sentence', ['chainid' => $chain->id], 'seq DESC, id DESC', 'id, userid', 0, 1);
        $lastwriter = $last ? (int) reset($last)->userid : 0;
        $everyone = (int) $this->instance->completionmode === self::COMPLETE_EVERYONE;

        $ranked = [];
        foreach ($candidates as $userid => $participant) {
            $inchain = (int) ($counts[$userid] ?? 0);
            if ($everyone && $inchain > 0) {
                continue;
            }
            $ranked[] = [
                (int) $participant->turns,
                $userid === $lastwriter ? 1 : 0,
                $inchain,
                (int) $participant->waitingsince,
                $userid,
            ];
        }
        if (!$ranked) {
            return null;
        }
        sort($ranked);
        return $ranked[0][4];
    }

    /**
     * Whether a chain has reached its completion rule.
     *
     * @param stdClass $chain The chain.
     * @return bool
     */
    public function is_complete(stdClass $chain): bool {
        global $DB;
        switch ((int) $this->instance->completionmode) {
            case self::COMPLETE_SENTENCES:
                $max = max(1, (int) $this->instance->maxsentences);
                return $DB->count_records('storychain_sentence', ['chainid' => $chain->id]) >= $max;
            case self::COMPLETE_EVERYONE:
                $eligible = $this->eligible_writers();
                if (!$eligible) {
                    return false;
                }
                $written = $DB->get_fieldset_select('storychain_sentence', 'DISTINCT userid', 'chainid = ?', [$chain->id]);
                return !array_diff($eligible, array_map('intval', $written));
            default:
                return false;
        }
    }

    /**
     * The users who may write in this group's chains.
     *
     * @return int[]
     */
    public function eligible_writers(): array {
        $users = get_enrolled_users($this->context, 'mod/storychain:write', $this->groupid, 'u.id', null, 0, 0, true);
        return array_map('intval', array_keys($users));
    }

    /**
     * How many writers in this group are online now.
     *
     * @return int
     */
    public function count_online(): int {
        global $DB;
        return $DB->count_records_select(
            'storychain_participant',
            'storychainid = :sid AND groupid = :gid AND lastseen >= :since',
            ['sid' => $this->instance->id, 'gid' => $this->groupid, 'since' => self::now() - self::ONLINE_WINDOW]
        );
    }

    /**
     * Add the writer's sentence to the chain whose turn they hold.
     *
     * @param int $userid The writer.
     * @param int $chainid The chain.
     * @param string $text The sentence.
     * @return stdClass The stored sentence.
     * @throws moodle_exception When the text is empty or too long, or it is not the user's turn.
     */
    public function submit(int $userid, int $chainid, string $text): stdClass {
        $text = self::clean_sentence($text);
        if ($text === '') {
            throw new moodle_exception('emptysentence', 'mod_storychain');
        }
        $max = max(1, (int) $this->instance->maxlength);
        if (core_text::strlen($text) > $max) {
            throw new moodle_exception('sentencetoolong', 'mod_storychain', '', $max);
        }
        $sentence = $this->with_lock(function () use ($userid, $chainid, $text) {
            global $DB;
            $now = self::now();
            $chain = $this->require_turn($userid, $chainid, $now);
            $seq = (int) $DB->get_field_sql('SELECT MAX(seq) FROM {storychain_sentence} WHERE chainid = ?', [$chain->id]);
            $sentence = (object) [
                'chainid' => $chain->id,
                'userid' => $userid,
                'seq' => $seq + 1,
                'content' => $text,
                'hidden' => 0,
                'timecreated' => $now,
            ];
            $sentence->id = $DB->insert_record('storychain_sentence', $sentence);
            $this->release($chain, $this->get_participant($userid), true);
            if ($this->is_complete($chain)) {
                $this->finish($chain);
            }
            $this->do_tick();
            return $sentence;
        }, true);

        \mod_storychain\event\sentence_added::create([
            'objectid' => $sentence->id,
            'context' => $this->context,
            'other' => ['chainid' => $chainid],
        ])->trigger();
        return $sentence;
    }

    /**
     * Give up the turn the writer holds.
     *
     * @param int $userid The writer.
     * @param int $chainid The chain.
     */
    public function pass(int $userid, int $chainid): void {
        $this->with_lock(function () use ($userid, $chainid) {
            $chain = $this->require_turn($userid, $chainid, self::now());
            $this->release($chain, $this->get_participant($userid), true);
            $this->do_tick();
            return true;
        }, true);
    }

    /**
     * Normalise a submitted sentence: no tags, one line, single spaces.
     *
     * @param string $text The raw text.
     * @return string
     */
    public static function clean_sentence(string $text): string {
        $text = clean_param($text, PARAM_NOTAGS);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * A chain whose turn the user holds right now.
     *
     * @param int $userid The writer.
     * @param int $chainid The chain.
     * @param int $now The time.
     * @return stdClass
     * @throws moodle_exception When it is not the user's turn.
     */
    protected function require_turn(int $userid, int $chainid, int $now): stdClass {
        $chain = $this->get_chain($chainid);
        if (
            (int) $chain->status !== self::STATUS_WRITING || (int) $chain->writerid !== $userid
                || $now > (int) $chain->claimexpires + self::SUBMIT_GRACE
        ) {
            throw new moodle_exception('notyourturn', 'mod_storychain');
        }
        return $chain;
    }

    /**
     * A writer's participant row in this group.
     *
     * @param int $userid The writer.
     * @return stdClass|null
     */
    protected function get_participant(int $userid): ?stdClass {
        global $DB;
        return $DB->get_record('storychain_participant', [
            'storychainid' => $this->instance->id,
            'groupid' => $this->groupid,
            'userid' => $userid,
        ]) ?: null;
    }

    /**
     * End the turn on a chain.
     *
     * @param stdClass $chain The chain (updated in place).
     * @param stdClass|null $writer The writer's participant row.
     * @param bool $count Count the turn towards the writer's fairness total and send them to the back.
     */
    protected function release(stdClass $chain, ?stdClass $writer, bool $count): void {
        global $DB;
        $now = self::now();
        $chain->writerid = 0;
        $chain->claimstarted = 0;
        $chain->claimexpires = 0;
        $chain->timemodified = $now;
        $DB->update_record('storychain_chain', $chain);
        if ($writer && $count) {
            $DB->execute(
                'UPDATE {storychain_participant} SET turns = turns + 1, waitingsince = :now WHERE id = :id',
                ['now' => $now, 'id' => $writer->id]
            );
        }
    }

    /**
     * Mark a chain finished (its turn, if any, is dropped without counting against the writer).
     *
     * @param stdClass $chain The chain (updated in place).
     */
    protected function finish(stdClass $chain): void {
        global $DB;
        $chain->status = self::STATUS_FINISHED;
        $chain->writerid = 0;
        $chain->claimstarted = 0;
        $chain->claimexpires = 0;
        $chain->timemodified = self::now();
        $DB->update_record('storychain_chain', $chain);
    }

    /**
     * Apply a teacher action to a chain.
     *
     * @param int $chainid The chain.
     * @param string $action close, reopen, reveal, present, next, prev, showall or stop.
     * @return stdClass The updated chain.
     * @throws moodle_exception For an unknown action.
     */
    public function manage(int $chainid, string $action): stdClass {
        return $this->with_lock(function () use ($chainid, $action) {
            global $DB;
            $chain = $this->get_chain($chainid);
            $now = self::now();
            switch ($action) {
                case 'close':
                    if ((int) $chain->status === self::STATUS_WRITING) {
                        $this->finish($chain);
                    }
                    break;
                case 'reopen':
                    $chain->status = self::STATUS_WRITING;
                    $chain->presenting = 0;
                    break;
                case 'reveal':
                    $chain->status = self::STATUS_REVEALED;
                    $chain->writerid = 0;
                    $chain->claimstarted = 0;
                    $chain->claimexpires = 0;
                    break;
                case 'present':
                    $DB->set_field('storychain_chain', 'presenting', 0, [
                        'storychainid' => $this->instance->id,
                        'groupid' => $this->groupid,
                    ]);
                    $chain->status = self::STATUS_REVEALED;
                    $chain->writerid = 0;
                    $chain->claimstarted = 0;
                    $chain->claimexpires = 0;
                    $chain->presenting = 1;
                    $chain->presentpos = 0;
                    break;
                case 'next':
                case 'prev':
                case 'showall':
                    $total = count($this->story_items($chain));
                    $pos = (int) $chain->presentpos;
                    $pos = match ($action) {
                        'next' => $pos + 1,
                        'prev' => $pos - 1,
                        default => $total,
                    };
                    $chain->presentpos = max(0, min($total, $pos));
                    break;
                case 'stop':
                    $chain->presenting = 0;
                    break;
                default:
                    throw new moodle_exception('invalidaction', 'mod_storychain');
            }
            $chain->timemodified = $now;
            $DB->update_record('storychain_chain', $chain);
            if ($action === 'reopen') {
                $this->do_tick();
            }
            return $chain;
        }, true);
    }

    /**
     * Hide or show one sentence.
     *
     * @param int $sentenceid The sentence.
     * @param bool $hidden Hide it.
     * @return stdClass The sentence.
     * @throws moodle_exception When the sentence is not in this group's chains.
     */
    public function set_hidden(int $sentenceid, bool $hidden): stdClass {
        global $DB;
        $sentence = $DB->get_record('storychain_sentence', ['id' => $sentenceid]);
        if (!$sentence) {
            throw new moodle_exception('invalidsentence', 'mod_storychain');
        }
        $chain = $this->get_chain((int) $sentence->chainid);
        $DB->set_field('storychain_sentence', 'hidden', $hidden ? 1 : 0, ['id' => $sentence->id]);
        $sentence->hidden = $hidden ? 1 : 0;
        // Keep the presenter's place inside the story when it shrinks.
        $total = count($this->story_items($chain));
        if ((int) $chain->presentpos > $total) {
            $DB->set_field('storychain_chain', 'presentpos', $total, ['id' => $chain->id]);
        }
        return $sentence;
    }

    /**
     * Run a function while holding this activity and group's lock.
     *
     * @param callable $fn The function.
     * @param bool $required Throw when the lock cannot be had (else return null).
     * @return mixed What the function returned.
     * @throws moodle_exception When the lock is required and busy.
     */
    protected function with_lock(callable $fn, bool $required) {
        $factory = \core\lock\lock_config::get_lock_factory('mod_storychain');
        $lock = $factory->get_lock('chains_' . $this->instance->id . '_' . $this->groupid, $required ? 5 : 1);
        if (!$lock) {
            if ($required) {
                throw new moodle_exception('busy', 'mod_storychain');
            }
            return null;
        }
        try {
            return $fn();
        } finally {
            $lock->release();
        }
    }
}
