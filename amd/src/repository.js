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
 * Web-service calls of the story chain views.
 *
 * Every call is wrapped in Promise.resolve(): core/ajax returns a jQuery Deferred.
 *
 * @module     mod_storychain/repository
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Call one web service.
 *
 * @param {String} methodname The function.
 * @param {Object} args The arguments.
 * @returns {Promise}
 */
const call = (methodname, args) => Promise.resolve(Ajax.call([{methodname, args}])[0]);

/**
 * The student's state (also their heartbeat).
 *
 * @param {Number} cmid Course module id.
 * @returns {Promise}
 */
export const getStudentState = (cmid) => call('mod_storychain_get_student_state', {cmid});

/**
 * Add a sentence.
 *
 * @param {Number} cmid Course module id.
 * @param {Number} chainid Chain id.
 * @param {String} text The sentence.
 * @returns {Promise}
 */
export const submitSentence = (cmid, chainid, text) => call('mod_storychain_submit_sentence', {cmid, chainid, text});

/**
 * Pass the turn on.
 *
 * @param {Number} cmid Course module id.
 * @param {Number} chainid Chain id.
 * @returns {Promise}
 */
export const passTurn = (cmid, chainid) => call('mod_storychain_pass_turn', {cmid, chainid});

/**
 * The teacher's state.
 *
 * @param {Number} cmid Course module id.
 * @param {Number} groupid Group id.
 * @returns {Promise}
 */
export const getTeacherState = (cmid, groupid) => call('mod_storychain_get_teacher_state', {cmid, groupid});

/**
 * A teacher action on a chain.
 *
 * @param {Number} cmid Course module id.
 * @param {Number} groupid Group id.
 * @param {Number} chainid Chain id.
 * @param {String} action close, reopen, reveal, present, next, prev, showall or stop.
 * @returns {Promise}
 */
export const manageChain = (cmid, groupid, chainid, action) =>
    call('mod_storychain_manage_chain', {cmid, groupid, chainid, action});

/**
 * Hide or show a sentence.
 *
 * @param {Number} cmid Course module id.
 * @param {Number} groupid Group id.
 * @param {Number} sentenceid Sentence id.
 * @param {Boolean} hidden Hide it.
 * @returns {Promise}
 */
export const setSentenceHidden = (cmid, groupid, sentenceid, hidden) =>
    call('mod_storychain_set_sentence_hidden', {cmid, groupid, sentenceid, hidden});
