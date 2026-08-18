<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Observer functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Observer handler.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Starts the grace period when a legacy account first signs in by any route.
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $event->objectid, 'deleted' => 0]);
        if ($user) {
            verification::bootstrap_existing_user($user);
        }
    }

    /**
     * Synchronises required channels after account creation or profile edits.
     */
    public static function user_changed(\core\event\base $event): void {
        global $DB;
        $user = $event->get_record_snapshot('user', $event->objectid)
            ?: $DB->get_record('user', ['id' => $event->objectid]);
        if ($user) {
            if ($event instanceof \core\event\user_created) {
                verification::track_new_user($user);
            } else {
                verification::ensure_for_user($user);
            }
        }
    }
}
