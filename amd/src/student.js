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
 * The student's screen: waiting, writing a sentence, following the teacher's presentation, reading stories.
 *
 * The turn form is rendered once per turn, so a state refresh never wipes what the student is typing.
 *
 * @module     mod_storychain/student
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import {getString} from 'core/str';
import Transport from 'mod_storychain/transport';
import * as Repository from 'mod_storychain/repository';

/** Seconds after a turn runs out in which the server still accepts it (chain_manager::SUBMIT_GRACE). */
const SUBMIT_GRACE = 15;

class StudentView {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root The view.
     * @param {Number} cmid Course module id.
     * @param {Object} config View config.
     */
    constructor(root, cmid, config) {
        this.root = root;
        this.cmid = cmid;
        this.region = root.querySelector('[data-region="student-state"]');
        this.announcer = root.querySelector('[data-region="announce"]');
        this.signature = null;
        this.status = null;
        this.expiresat = 0;
        this.ticker = null;
        this.busy = false;
        this.pagetitle = document.title;
        this.transport = new Transport(() => Repository.getStudentState(cmid), config);
        this.transport.subscribe((state) => this.render(state), () => null);
        root.addEventListener('submit', (e) => {
            if (e.target.matches('[data-region="sentence-form"]')) {
                e.preventDefault();
                this.submit(e.target);
            }
        });
        root.addEventListener('click', (e) => {
            const button = e.target.closest('[data-action="pass"]');
            if (button) {
                e.preventDefault();
                this.pass(button.closest('[data-region="sentence-form"]'));
            }
        });
        root.addEventListener('input', (e) => {
            if (e.target.matches('[data-region="sentence"]')) {
                this.updateCharCount(e.target);
            }
        });
        this.transport.start();
    }

    /**
     * What decides whether the screen must be rendered again.
     *
     * @param {Object} state The state.
     * @returns {String}
     */
    signatureOf(state) {
        if (state.status === 'writing') {
            return `writing:${state.turn.chainid}:${state.turn.claimstarted}`;
        }
        const copy = Object.assign({}, state, {turn: null});
        return JSON.stringify(copy);
    }

    /**
     * Show a state.
     *
     * @param {Object} state The state.
     * @returns {Promise}
     */
    async render(state) {
        this.setCountdown(state.turn);
        const signature = this.signatureOf(state);
        if (signature === this.signature) {
            return;
        }
        const previous = this.status;
        this.signature = signature;
        this.status = state.status;
        const context = Object.assign({}, state, {
            showstories: state.stories.length > 0 && state.status !== 'writing' && state.status !== 'presenting',
        });
        context['status_' + state.status] = true;
        const {html, js} = await Templates.renderForPromise('mod_storychain/student_state', context);
        Templates.replaceNodeContents(this.region, html, js);

        if (state.status === 'writing') {
            const textarea = this.region.querySelector('[data-region="sentence"]');
            if (textarea) {
                this.updateCharCount(textarea);
                textarea.focus();
            }
            if (previous !== 'writing') {
                this.announce(await getString('yourturn', 'mod_storychain'));
                document.title = await getString('yourturntitle', 'mod_storychain', this.pagetitle);
            }
        } else {
            document.title = this.pagetitle;
            if (state.status === 'presenting' && previous !== 'presenting') {
                this.announce(await getString('teacherisshowing', 'mod_storychain'));
            }
        }
        this.tick();
    }

    /**
     * Start or stop the turn's countdown.
     *
     * @param {Object} turn The turn.
     */
    setCountdown(turn) {
        clearInterval(this.ticker);
        if (!turn.active) {
            this.expiresat = 0;
            return;
        }
        this.expiresat = Date.now() + turn.secondsleft * 1000;
        this.ticker = setInterval(() => this.tick(), 1000);
        // Ask again once the server has certainly released the turn.
        this.transport.refreshIn((turn.secondsleft + SUBMIT_GRACE + 1) * 1000);
    }

    /**
     * Update the countdown text.
     */
    async tick() {
        const node = this.region.querySelector('[data-region="countdown"]');
        if (!node || !this.expiresat) {
            return;
        }
        const left = Math.max(0, Math.round((this.expiresat - Date.now()) / 1000));
        const minutes = Math.floor(left / 60);
        const seconds = String(left % 60).padStart(2, '0');
        node.textContent = left > 0
            ? await getString('timeleft', 'mod_storychain', `${minutes}:${seconds}`)
            : await getString('timeup', 'mod_storychain');
        node.classList.toggle('text-danger', left <= 15);
    }

    /**
     * Update the characters-left counter.
     *
     * @param {HTMLTextAreaElement} textarea The sentence box.
     */
    async updateCharCount(textarea) {
        const node = this.region.querySelector('[data-region="charcount"]');
        if (!node) {
            return;
        }
        const max = Number(textarea.getAttribute('maxlength')) || 0;
        node.textContent = await getString('charsleft', 'mod_storychain', Math.max(0, max - textarea.value.length));
    }

    /**
     * Send the sentence.
     *
     * @param {HTMLFormElement} form The form.
     */
    async submit(form) {
        const textarea = form.querySelector('[data-region="sentence"]');
        const text = textarea.value.trim();
        if (!text || this.busy) {
            return;
        }
        await this.act(form, () => Repository.submitSentence(this.cmid, Number(form.dataset.chainid), text), 'sentenceadded');
    }

    /**
     * Pass the turn on.
     *
     * @param {HTMLFormElement} form The form.
     */
    async pass(form) {
        if (!form || this.busy) {
            return;
        }
        await this.act(form, () => Repository.passTurn(this.cmid, Number(form.dataset.chainid)), 'turnpassed');
    }

    /**
     * Run a turn action and show the state it returns, or its error.
     *
     * @param {HTMLFormElement} form The form.
     * @param {Function} request Returns a Promise of the new state.
     * @param {String} donekey String to announce on success.
     */
    async act(form, request, donekey) {
        this.busy = true;
        const buttons = form.querySelectorAll('button');
        buttons.forEach((b) => {
            b.disabled = true;
        });
        const error = form.querySelector('[data-region="error"]');
        error.textContent = '';
        try {
            const state = await request();
            this.announce(await getString(donekey, 'mod_storychain'));
            await this.transport.deliver(state);
        } catch (e) {
            error.textContent = (e && e.message) ? e.message : String(e);
            buttons.forEach((b) => {
                b.disabled = false;
            });
            this.transport.refresh();
        } finally {
            this.busy = false;
        }
    }

    /**
     * Tell screen-reader users something.
     *
     * @param {String} text The message.
     */
    announce(text) {
        if (this.announcer) {
            this.announcer.textContent = text;
        }
    }
}

/**
 * Start the student's view.
 *
 * @param {Number} cmid Course module id.
 * @param {Object} config View config: interval, realtime, groupid.
 */
export const init = (cmid, config) => {
    const root = document.querySelector(`[data-region="mod-storychain-student"][data-cmid="${cmid}"]`);
    if (root) {
        new StudentView(root, cmid, config);
    }
};
