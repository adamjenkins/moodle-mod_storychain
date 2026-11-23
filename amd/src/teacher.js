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
 * The teacher's live view: every chain in full, with close, reveal, reopen, hide and present controls.
 *
 * @module     mod_storychain/teacher
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';
import Transport from 'mod_storychain/transport';
import * as Repository from 'mod_storychain/repository';

class TeacherView {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root The view.
     * @param {Number} cmid Course module id.
     * @param {Object} config View config: interval, realtime, groupid, presenturl.
     */
    constructor(root, cmid, config) {
        this.root = root;
        this.cmid = cmid;
        this.groupid = Number(config.groupid || 0);
        this.presenturl = config.presenturl;
        this.region = root.querySelector('[data-region="teacher-state"]');
        this.signature = null;
        this.deadlines = {};
        this.transport = new Transport(() => Repository.getTeacherState(cmid, this.groupid), config);
        this.transport.subscribe((state) => this.render(state), () => null);
        root.addEventListener('click', (e) => this.click(e));
        setInterval(() => this.tick(), 1000);
        this.transport.start();
    }

    /**
     * Show a state.
     *
     * @param {Object} state The state.
     * @returns {Promise}
     */
    async render(state) {
        const now = Date.now();
        this.deadlines = {};
        state.chains.forEach((c) => {
            this.deadlines[c.id] = now + c.secondsleft * 1000;
        });
        const signature = JSON.stringify(state.chains.map((c) => Object.assign({}, c, {secondsleft: 0})))
            + `:${state.online}:${state.eligible}`;
        if (signature === this.signature) {
            return;
        }
        this.signature = signature;
        const focus = this.focusKey();
        const context = Object.assign({}, state, {presenturl: this.presenturl});
        const {html, js} = await Templates.renderForPromise('mod_storychain/teacher_state', context);
        Templates.replaceNodeContents(this.region, html, js);
        this.restoreFocus(focus);
        this.tick();
    }

    /**
     * Update the "seconds left" counters between refreshes.
     */
    async tick() {
        const nodes = this.region.querySelectorAll('[data-region="secondsleft"]');
        for (const node of nodes) {
            const deadline = this.deadlines[Number(node.dataset.chainid)];
            if (deadline) {
                const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
                node.textContent = await getString('secondsleft', 'mod_storychain', left);
            }
        }
    }

    /**
     * Which control has the focus, so it can get it back after a render.
     *
     * @returns {String|null}
     */
    focusKey() {
        const active = document.activeElement;
        if (!active || !this.region.contains(active) || !active.dataset.action) {
            return null;
        }
        return `[data-action="${active.dataset.action}"]` +
            (active.dataset.chainid ? `[data-chainid="${active.dataset.chainid}"]` : '') +
            (active.dataset.sentenceid ? `[data-sentenceid="${active.dataset.sentenceid}"]` : '');
    }

    /**
     * Give the focus back.
     *
     * @param {String|null} key From focusKey().
     */
    restoreFocus(key) {
        if (!key) {
            return;
        }
        // A hide button becomes a show button (and back) once it has done its job.
        const node = this.region.querySelector(key)
            || this.region.querySelector(key.replace('"hide"', '"unhide"'))
            || this.region.querySelector(key.replace('"unhide"', '"hide"'));
        if (node) {
            node.focus();
        }
    }

    /**
     * Handle the controls.
     *
     * @param {Event} e The click.
     */
    async click(e) {
        const button = e.target.closest('button[data-action]');
        if (!button || !this.region.contains(button)) {
            return;
        }
        e.preventDefault();
        const action = button.dataset.action;
        button.disabled = true;
        try {
            let state;
            if (action === 'hide' || action === 'unhide') {
                state = await Repository.setSentenceHidden(this.cmid, this.groupid, Number(button.dataset.sentenceid),
                    action === 'hide');
            } else {
                state = await Repository.manageChain(this.cmid, this.groupid, Number(button.dataset.chainid), action);
            }
            this.signature = null;
            await this.transport.deliver(state);
        } catch (error) {
            button.disabled = false;
            Notification.exception(error);
        }
    }
}

/**
 * Start the teacher's live view.
 *
 * @param {Number} cmid Course module id.
 * @param {Object} config View config.
 */
export const init = (cmid, config) => {
    const root = document.querySelector(`[data-region="mod-storychain-teacher"][data-cmid="${cmid}"]`);
    if (root) {
        new TeacherView(root, cmid, config);
    }
};
