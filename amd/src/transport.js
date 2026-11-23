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
 * Keeps a view's state fresh: polls a web service, and with tool_realtime also refreshes at once on a ping.
 *
 * With tool_realtime the poll becomes a slow heartbeat (it still marks the writer as online and lets the
 * server hand out turns that ran out). Pings carry no data; the state always comes from the web service.
 *
 * @module     mod_storychain/transport
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as PubSub from 'core/pubsub';

/** PubSub event name of tool_realtime (tool_realtime/events), used as a string so it is not a dependency. */
const REALTIME_EVENT = 'tool-realtime-event';
/** Heartbeat while tool_realtime delivers changes. */
const REALTIME_HEARTBEAT = 15000;
/** Longest wait while the tab is hidden: the server counts a writer offline after 45 seconds. */
const HIDDEN_MAX = 20000;

export default class Transport {
    /**
     * Constructor.
     *
     * @param {Function} fetcher Returns a Promise of the state.
     * @param {Object} config View config: interval (ms), realtime (bool), groupid.
     */
    constructor(fetcher, config) {
        this.fetcher = fetcher;
        this.interval = config.realtime ? REALTIME_HEARTBEAT : Math.max(1000, config.interval || 3000);
        this.groupid = Number(config.groupid || 0);
        this.listeners = [];
        this.errorlisteners = [];
        this.timer = null;
        this.inflight = false;
        this.again = false;
        this.running = false;
        if (config.realtime) {
            PubSub.subscribe(REALTIME_EVENT, (event) => {
                if (event && event.component === 'mod_storychain' && event.area === 'group'
                        && Number(event.itemid) === this.groupid) {
                    this.refresh();
                }
            });
        }
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refresh();
            }
        });
    }

    /**
     * Listen for states.
     *
     * @param {Function} listener Called with each state; may return a Promise.
     * @param {Function} [onerror] Called with errors.
     */
    subscribe(listener, onerror) {
        this.listeners.push(listener);
        if (onerror) {
            this.errorlisteners.push(onerror);
        }
    }

    /**
     * Start.
     */
    start() {
        this.running = true;
        this.refresh();
    }

    /**
     * Stop.
     */
    stop() {
        this.running = false;
        clearTimeout(this.timer);
    }

    /**
     * Hand a state that arrived another way (the reply to an action) to the listeners.
     *
     * @param {Object} state The state.
     * @returns {Promise}
     */
    deliver(state) {
        return Promise.all(this.listeners.map((l) => l(state)));
    }

    /**
     * Fetch now (or straight after the request in flight).
     */
    refresh() {
        if (!this.running) {
            return;
        }
        if (this.inflight) {
            this.again = true;
            return;
        }
        clearTimeout(this.timer);
        this.inflight = true;
        this.fetcher().then((state) => this.deliver(state)).catch((error) => {
            this.errorlisteners.forEach((l) => l(error));
        }).then(() => {
            this.inflight = false;
            if (this.again) {
                this.again = false;
                this.refresh();
            } else {
                this.schedule();
            }
            return null;
        }).catch(() => null);
    }

    /**
     * Fetch again after a given time, if that is sooner than the next scheduled fetch.
     *
     * @param {Number} ms Milliseconds.
     */
    refreshIn(ms) {
        clearTimeout(this.soon);
        this.soon = setTimeout(() => this.refresh(), Math.max(0, ms));
    }

    /**
     * Schedule the next fetch.
     */
    schedule() {
        if (!this.running) {
            return;
        }
        const wait = document.hidden ? Math.min(this.interval * 5, Math.max(this.interval, HIDDEN_MAX)) : this.interval;
        this.timer = setTimeout(() => this.refresh(), wait);
    }
}
