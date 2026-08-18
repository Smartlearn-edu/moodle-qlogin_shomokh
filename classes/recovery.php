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
 * Recovery functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Implements a self-initiated WhatsApp recovery handshake.
 */
final class recovery {
    /**
     * Creates a recovery request without exposing whether a phone exists.
     */
    public static function start(string $phone): ?array {
        global $DB, $SESSION;
        if (!(bool)get_config('local_qlogin_shomokh', 'recoveryenabled')) {
            return null;
        }
        $phone = manager::normalise_phone($phone);
        $username = $phone === '' ? null : migration::resolve_phone_login($phone);
        $user = $username === null ? false : $DB->get_record('user', [
            'username' => $username,
            'deleted' => 0,
            'suspended' => 0,
        ]);
        if (!$user) {
            unset($SESSION->local_qlogin_shomokh_recoveryid);
            unset($SESSION->local_qlogin_shomokh_recoverycode);
            return null;
        }
        $latestrecords = $DB->get_records(
            'local_qlogin_shomokh_recov',
            ['userid' => $user->id],
            'timecreated DESC',
            '*',
            0,
            1
        );
        $latest = reset($latestrecords);
        $cooldown = max(60, (int)get_config('local_qlogin_shomokh', 'resendcooldown'));
        if ($latest && time() - $latest->timecreated < $cooldown) {
            // Keep the response indistinguishable from an unknown phone number.
            unset($SESSION->local_qlogin_shomokh_recoveryid);
            unset($SESSION->local_qlogin_shomokh_recoverycode);
            return null;
        }
        $code = manager::generate_code();
        $minutes = max(5, min(60, (int)get_config('local_qlogin_shomokh', 'recoveryexpiryminutes')));
        $record = (object)[
            'userid' => $user->id, 'phone' => $phone, 'tokenhash' => manager::hash_token($code),
            'state' => 'pending', 'expiresat' => time() + ($minutes * MINSECS), 'verifiedat' => null,
            'attempts' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('local_qlogin_shomokh_recov', $record);
        $SESSION->local_qlogin_shomokh_recoveryid = $record->id;
        return [$record, $code];
    }

    /**
     * Returns the current browser's recovery record.
     */
    public static function current() {
        global $DB, $SESSION;
        $id = isset($SESSION->local_qlogin_shomokh_recoveryid)
            ? (int)$SESSION->local_qlogin_shomokh_recoveryid : 0;
        return $id ? $DB->get_record('local_qlogin_shomokh_recov', ['id' => $id]) : false;
    }

    /**
     * Confirms a RESET message after webhook authentication.
     */
    public static function verify_from_whatsapp(string $fromphone, string $message): array {
        global $DB;
        if (!(bool)get_config('local_qlogin_shomokh', 'recoveryenabled')) {
            return ['status' => 'recoverydisabled', 'userid' => null];
        }
        $phone = manager::normalise_phone($fromphone);
        if ($phone === '' || !preg_match('/\bSHOMOKH\s+RESET\s+([A-HJ-NP-Z2-9]{10})\b/i', trim($message), $matches)) {
            return ['status' => 'invalidreset', 'userid' => null];
        }
        $records = $DB->get_records_select(
            'local_qlogin_shomokh_recov',
            'phone = :phone AND state = :state AND expiresat >= :now',
            ['phone' => $phone, 'state' => 'pending', 'now' => time()],
            'timecreated DESC'
        );
        foreach ($records as $record) {
            if (hash_equals($record->tokenhash, manager::hash_token($matches[1]))) {
                $record->state = 'verified';
                $record->verifiedat = time();
                $record->tokenhash = manager::hash_token(manager::generate_code());
                $record->timemodified = time();
                $DB->update_record('local_qlogin_shomokh_recov', $record);
                return ['status' => 'recoveryverified', 'userid' => $record->userid];
            }
            $record->attempts++;
            $record->timemodified = time();
            $DB->update_record('local_qlogin_shomokh_recov', $record);
        }
        return ['status' => 'invalidreset', 'userid' => null];
    }

    /**
     * Changes the password after the WhatsApp handshake.
     */
    public static function reset_password(string $password): bool {
        global $CFG, $DB, $SESSION;
        if (!(bool)get_config('local_qlogin_shomokh', 'recoveryenabled') || $password === '') {
            return false;
        }
        $record = self::current();
        if (!$record || $record->state !== 'verified' || $record->expiresat < time()) {
            return false;
        }
        $user = $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0]);
        if (!$user) {
            return false;
        }
        require_once($CFG->libdir . '/moodlelib.php');
        update_internal_user_password($user, $password);
        $record->state = 'used';
        $record->timemodified = time();
        $DB->update_record('local_qlogin_shomokh_recov', $record);
        unset($SESSION->local_qlogin_shomokh_recoveryid);
        unset($SESSION->local_qlogin_shomokh_recoverycode);
        return true;
    }
}
