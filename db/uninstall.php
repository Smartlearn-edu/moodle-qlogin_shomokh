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
 * Uninstall functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Releases only restrictions tracked as having been applied by this plugin.
 */
function xmldb_local_qlogin_shomokh_uninstall(): bool {
    global $DB;
    $dbman = $DB->get_manager();
    $hasaccountlocks = $dbman->table_exists('local_qlogin_shomokh_lock');
    $hasenrolmentlocks = $dbman->table_exists('local_qlogin_shomokh_enlock');
    if (!$hasaccountlocks && !$hasenrolmentlocks) {
        return true;
    }
    $userids = [];
    if ($hasaccountlocks) {
        foreach ($DB->get_records('local_qlogin_shomokh_lock') as $lock) {
            $userids[(int)$lock->userid] = true;
        }
    }
    if ($hasenrolmentlocks) {
        foreach ($DB->get_records('local_qlogin_shomokh_enlock') as $lock) {
            $userids[(int)$lock->userid] = true;
        }
    }
    foreach (array_keys($userids) as $userid) {
        \local_qlogin_shomokh\enforcement::release($userid);
    }
    return true;
}
