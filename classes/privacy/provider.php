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

namespace mod_storychain\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_storychain: sentences, turn counts and presence.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored.
     *
     * @param collection $collection The collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('storychain_sentence', [
            'userid' => 'privacy:metadata:storychain_sentence:userid',
            'content' => 'privacy:metadata:storychain_sentence:content',
            'hidden' => 'privacy:metadata:storychain_sentence:hidden',
            'timecreated' => 'privacy:metadata:storychain_sentence:timecreated',
        ], 'privacy:metadata:storychain_sentence');
        $collection->add_database_table('storychain_participant', [
            'userid' => 'privacy:metadata:storychain_participant:userid',
            'turns' => 'privacy:metadata:storychain_participant:turns',
            'lastseen' => 'privacy:metadata:storychain_participant:lastseen',
            'waitingsince' => 'privacy:metadata:storychain_participant:waitingsince',
        ], 'privacy:metadata:storychain_participant');
        $collection->add_database_table('storychain_chain', [
            'writerid' => 'privacy:metadata:storychain_chain:writerid',
            'claimstarted' => 'privacy:metadata:storychain_chain:claimstarted',
        ], 'privacy:metadata:storychain_chain');
        return $collection;
    }

    /**
     * SQL joining a module context to its storychain row, as "s".
     *
     * @return string
     */
    protected static function context_join(): string {
        return "FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                JOIN {storychain} s ON s.id = cm.instance";
    }

    /**
     * Contexts holding data about a user.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'storychain', 'userid' => $userid];
        $join = self::context_join();
        $contextlist->add_from_sql("SELECT ctx.id $join
            JOIN {storychain_chain} c ON c.storychainid = s.id
            JOIN {storychain_sentence} se ON se.chainid = c.id
            WHERE se.userid = :userid", $params);
        $contextlist->add_from_sql("SELECT ctx.id $join
            JOIN {storychain_participant} p ON p.storychainid = s.id
            WHERE p.userid = :userid", $params);
        $contextlist->add_from_sql("SELECT ctx.id $join
            JOIN {storychain_chain} c ON c.storychainid = s.id
            WHERE c.writerid = :userid", $params);
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist The list to fill.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'storychain', 'contextid' => $context->id];
        $join = self::context_join();
        $userlist->add_from_sql('userid', "SELECT se.userid $join
            JOIN {storychain_chain} c ON c.storychainid = s.id
            JOIN {storychain_sentence} se ON se.chainid = c.id
            WHERE ctx.id = :contextid", $params);
        $userlist->add_from_sql('userid', "SELECT p.userid $join
            JOIN {storychain_participant} p ON p.storychainid = s.id
            WHERE ctx.id = :contextid", $params);
        $userlist->add_from_sql('writerid', "SELECT c.writerid $join
            JOIN {storychain_chain} c ON c.storychainid = s.id
            WHERE ctx.id = :contextid AND c.writerid > 0", $params);
    }

    /**
     * The storychain instance id of a module context, or null.
     *
     * @param context $context The context.
     * @return int|null
     */
    protected static function instance_id(context $context): ?int {
        if (!$context instanceof context_module) {
            return null;
        }
        $cm = get_coursemodule_from_id('storychain', $context->instanceid);
        return $cm ? (int) $cm->instance : null;
    }

    /**
     * Export a user's sentences and turn data.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $instanceid = self::instance_id($context);
            if (!$instanceid) {
                continue;
            }
            $sentences = $DB->get_records_sql(
                "SELECT se.id, se.seq, se.content, se.hidden, se.timecreated, c.chainindex, c.title, c.groupid
                   FROM {storychain_sentence} se
                   JOIN {storychain_chain} c ON c.id = se.chainid
                  WHERE c.storychainid = :sid AND se.userid = :userid
               ORDER BY c.groupid, c.chainindex, se.seq",
                ['sid' => $instanceid, 'userid' => $userid]
            );
            $participants = $DB->get_records('storychain_participant', ['storychainid' => $instanceid, 'userid' => $userid]);
            if (!$sentences && !$participants) {
                continue;
            }
            $data = helper::get_context_data($context, $contextlist->get_user());
            $data->sentences = array_values(array_map(fn($s) => [
                'story' => trim((string) $s->title) !== '' ? $s->title
                    : get_string('storyn', 'mod_storychain', (int) $s->chainindex + 1),
                'position' => (int) $s->seq,
                'sentence' => $s->content,
                'hidden' => transform::yesno($s->hidden),
                'timecreated' => transform::datetime($s->timecreated),
            ], $sentences));
            $data->turns = array_values(array_map(fn($p) => [
                'turns' => (int) $p->turns,
                'lastseen' => transform::datetime($p->lastseen),
                'waitingsince' => transform::datetime($p->waitingsince),
            ], $participants));
            helper::export_context_files($context, $contextlist->get_user());
            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Delete everyone's data in a context.
     *
     * @param context $context The context.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $CFG;
        $instanceid = self::instance_id($context);
        if (!$instanceid) {
            return;
        }
        require_once($CFG->dirroot . '/mod/storychain/lib.php');
        storychain_delete_stories($instanceid);
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $instanceid = self::instance_id($context);
            if ($instanceid) {
                self::delete_users($instanceid, [$userid]);
            }
        }
    }

    /**
     * Delete several users' data in one context.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $instanceid = self::instance_id($userlist->get_context());
        if ($instanceid && $userlist->get_userids()) {
            self::delete_users($instanceid, $userlist->get_userids());
        }
    }

    /**
     * Delete the sentences, participant rows and turns of some users in one instance.
     *
     * @param int $instanceid The storychain id.
     * @param int[] $userids The users.
     */
    protected static function delete_users(int $instanceid, array $userids): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['sid'] = $instanceid;
        $DB->delete_records_select(
            'storychain_sentence',
            "userid $insql AND chainid IN (SELECT id FROM {storychain_chain} WHERE storychainid = :sid)",
            $params
        );
        $DB->delete_records_select('storychain_participant', "userid $insql AND storychainid = :sid", $params);
        $DB->execute(
            "UPDATE {storychain_chain} SET writerid = 0, claimstarted = 0, claimexpires = 0
              WHERE storychainid = :sid AND writerid $insql",
            $params
        );
    }
}
