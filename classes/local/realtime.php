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

/**
 * Optional instant updates through tool_realtime (Marina Glancy's real-time plugin).
 *
 * The channel only carries an empty "something changed" ping, one channel per activity and group. Each
 * browser then fetches its own state through the web services, so nothing role-specific (sentences,
 * whose turn it is) is ever pushed. Without tool_realtime every browser simply polls.
 *
 * @package    mod_storychain
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtime {
    /** @var string Channel area. */
    const AREA = 'group';

    /**
     * Whether tool_realtime is installed and has a working backend.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists('\tool_realtime\manager') && class_exists('\tool_realtime\channel')
            && \tool_realtime\manager::is_enabled('mod_storychain');
    }

    /**
     * Whether tool_realtime is installed at all (it may still lack a backend).
     *
     * @return bool
     */
    public static function is_installed(): bool {
        return class_exists('\tool_realtime\manager');
    }

    /**
     * Subscribe the page being rendered to a group's channel.
     *
     * @param context_module $context The module context.
     * @param int $groupid The group.
     * @return bool Whether the page will receive pings.
     */
    public static function subscribe(context_module $context, int $groupid): bool {
        if (!self::is_available()) {
            return false;
        }
        (new \tool_realtime\channel($context, 'mod_storychain', self::AREA, $groupid))->subscribe();
        return true;
    }

    /**
     * Tell every open page of a group to fetch its state again.
     *
     * @param context_module $context The module context.
     * @param int $groupid The group.
     */
    public static function notify(context_module $context, int $groupid): void {
        if (!self::is_available()) {
            return;
        }
        try {
            (new \tool_realtime\channel($context, 'mod_storychain', self::AREA, $groupid))->notify([]);
        } catch (\Throwable $e) {
            // Polling picks the change up anyway.
            debugging('mod_storychain: real-time notify failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
