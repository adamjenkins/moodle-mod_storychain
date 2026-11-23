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
 * The projector view: step through a story one sentence at a time; students' open screens follow.
 *
 * @module     mod_storychain/present
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import Notification from 'core/notification';
import Transport from 'mod_storychain/transport';
import * as Repository from 'mod_storychain/repository';

class PresentView {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root The view.
     * @param {Number} cmid Course module id.
     * @param {Object} config groupid, chainid, interval, realtime, viewurl.
     */
    constructor(root, cmid, config) {
        this.root = root;
        this.cmid = cmid;
        this.groupid = Number(config.groupid || 0);
        this.chainid = Number(config.chainid);
        this.viewurl = config.viewurl;
        this.region = root.querySelector('[data-region="present-state"]');
        this.signature = null;
        this.busy = false;
        this.started = false;
        this.transport = new Transport(() => Repository.getTeacherState(cmid, this.groupid), config);
        this.transport.subscribe((state) => this.render(state), () => null);
        root.addEventListener('click', (e) => {
            const button = e.target.closest('button[data-action]');
            if (button) {
                e.preventDefault();
                this.act(button.dataset.action);
            }
        });
        document.addEventListener('keydown', (e) => this.key(e));
        this.start();
    }

    /**
     * Start presenting this chain (unless it already is, e.g. after a reload), then keep in sync.
     */
    async start() {
        try {
            const state = await Repository.getTeacherState(this.cmid, this.groupid);
            const chain = state.chains.find((c) => c.id === this.chainid);
            if (chain && !chain.presenting) {
                await this.transport.deliver(await Repository.manageChain(this.cmid, this.groupid, this.chainid, 'present'));
            } else {
                await this.transport.deliver(state);
            }
        } catch (error) {
            Notification.exception(error);
        }
        this.transport.start();
    }

    /**
     * Show a state.
     *
     * @param {Object} state The state.
     * @returns {Promise}
     */
    async render(state) {
        const chain = state.chains.find((c) => c.id === this.chainid) || null;
        const signature = JSON.stringify(chain ? Object.assign({}, chain, {secondsleft: 0}) : null);
        if (signature !== this.signature) {
            this.signature = signature;
            const {html, js} = await Templates.renderForPromise('mod_storychain/present_state', {chain});
            Templates.replaceNodeContents(this.region, html, js);
            const latest = this.region.querySelector('.mod-storychain-latest');
            if (latest) {
                latest.scrollIntoView({block: 'nearest', behavior: 'smooth'});
            }
        }
        const buttons = (action) => this.root.querySelector(`button[data-action="${action}"]`);
        if (chain) {
            buttons('prev').disabled = chain.atstart;
            buttons('next').disabled = chain.atend;
            buttons('showall').disabled = chain.atend;
        }
    }

    /**
     * Keyboard: right arrow, space or Page Down for next; left arrow or Page Up for previous.
     *
     * @param {KeyboardEvent} e The key press.
     */
    key(e) {
        if (e.altKey || e.ctrlKey || e.metaKey || e.target.closest('input, textarea, select, a, button')) {
            return;
        }
        if (['ArrowRight', 'PageDown', ' '].includes(e.key)) {
            e.preventDefault();
            this.act('next');
        } else if (['ArrowLeft', 'PageUp'].includes(e.key)) {
            e.preventDefault();
            this.act('prev');
        }
    }

    /**
     * Run a presentation action.
     *
     * @param {String} action next, prev, showall or stop.
     */
    async act(action) {
        if (this.busy) {
            return;
        }
        this.busy = true;
        try {
            const state = await Repository.manageChain(this.cmid, this.groupid, this.chainid, action);
            if (action === 'stop') {
                this.transport.stop();
                window.location.href = this.viewurl;
                return;
            }
            await this.transport.deliver(state);
        } catch (error) {
            Notification.exception(error);
        } finally {
            this.busy = false;
        }
    }
}

/**
 * Start the projector view.
 *
 * @param {Number} cmid Course module id.
 * @param {Object} config groupid, chainid, interval, realtime, viewurl.
 */
export const init = (cmid, config) => {
    const root = document.querySelector(`[data-region="mod-storychain-present"][data-cmid="${cmid}"]`);
    if (root) {
        new PresentView(root, cmid, config);
    }
};
