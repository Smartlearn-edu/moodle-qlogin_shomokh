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

/** Independent email password recovery. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Creates and consumes privacy-minimised, one-time email reset links. */
final class email_recovery {
    /** Whether the independent flow and its schema are ready. */
    public static function available(): bool {
        global $DB;
        return (bool)get_config('local_qlogin_shomokh', 'emailrecoveryenabled')
            && $DB->get_manager()->table_exists('local_qlogin_shomokh_reset');
    }

    /** Requests recovery without exposing account existence or provider status. */
    public static function start(string $email): void {
        global $DB;
        if (!self::available()) {
            return;
        }
        $email = manager::normalise_email($email);
        if ($email === '') {
            return;
        }
        $emailsql = $DB->sql_equal('email', ':email', false);
        $users = $DB->get_records_select(
            'user',
            "$emailsql AND deleted = :deleted AND suspended = :suspended",
            ['email' => $email, 'deleted' => 0, 'suspended' => 0],
            'id ASC',
            '*',
            0,
            2
        );
        if (count($users) !== 1) {
            return;
        }
        $user = reset($users);
        if (!self::eligible_user($user)) {
            return;
        }
        $targethash = \local_qlogin_shomokh\mail\config::hash_identifier($email);
        $latestrecords = $DB->get_records(
            'local_qlogin_shomokh_reset',
            ['targethash' => $targethash],
            'timecreated DESC',
            '*',
            0,
            1
        );
        $latest = reset($latestrecords);
        $cooldown = max(60, min(DAYSECS, (int)get_config('local_qlogin_shomokh', 'resendcooldown')));
        if ($latest && time() - (int)$latest->timecreated < $cooldown) {
            return;
        }
        $now = time();
        $minutes = max(5, min(60, (int)get_config('local_qlogin_shomokh', 'recoveryexpiryminutes')));
        $record = (object)[
            'userid' => $user->id,
            'channel' => 'email',
            'targethash' => $targethash,
            'tokenhash' => hash('sha256', random_bytes(32)),
            'state' => 'pending',
            'expiresat' => $now + ($minutes * MINSECS),
            'attempts' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('local_qlogin_shomokh_reset', $record);
        $token = \local_qlogin_shomokh\mail\config::derive_token(
            'recovery',
            (int)$record->id,
            (int)$user->id,
            $now
        );
        $record->tokenhash = manager::hash_token($token);
        $DB->update_record('local_qlogin_shomokh_reset', $record);
        $result = self::send_record($record, $user, $email, $token);
        if (!$result->accepted && $result->retryable) {
            \local_qlogin_shomokh\mail\service::queue_retry('recovery', (int)$record->id, 1);
        }
    }

    /** Retries the same deterministic link without storing its raw value in task data. */
    public static function retry(int $recordid, int $attempt): void {
        global $DB;
        $record = $DB->get_record('local_qlogin_shomokh_reset', ['id' => $recordid, 'state' => 'pending']);
        $user = $record ? $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0, 'suspended' => 0]) : false;
        if (!$record || !$user || $record->expiresat < time() || !self::eligible_user($user)) {
            return;
        }
        $email = manager::normalise_email((string)$user->email);
        if (
            $email === '' || !hash_equals(
                $record->targethash,
                \local_qlogin_shomokh\mail\config::hash_identifier($email)
            )
        ) {
            return;
        }
        $token = \local_qlogin_shomokh\mail\config::derive_token(
            'recovery',
            (int)$record->id,
            (int)$user->id,
            (int)$record->timecreated
        );
        if (!hash_equals($record->tokenhash, manager::hash_token($token))) {
            return;
        }
        $result = self::send_record($record, $user, $email, $token);
        if (!$result->accepted && $result->retryable) {
            \local_qlogin_shomokh\mail\service::queue_retry('recovery', $recordid, $attempt);
        }
    }

    /** Returns a valid pending request for a raw link token. */
    public static function find(string $token) {
        global $DB;
        if (!self::available() || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return false;
        }
        return $DB->get_record_select(
            'local_qlogin_shomokh_reset',
            'tokenhash = :tokenhash AND state = :state AND expiresat >= :now',
            [
                'tokenhash' => manager::hash_token($token),
                'state' => 'pending',
                'now' => time(),
            ]
        );
    }

    /** Changes the password once and invalidates existing sessions. */
    public static function consume(string $token, string $password): bool {
        global $CFG, $DB;
        if ($password === '') {
            return false;
        }
        $tokenhash = manager::hash_token($token);
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_qlogin_shomokh');
        $lock = $lockfactory->get_lock('emailreset_' . substr($tokenhash, 0, 32), 5);
        if (!$lock) {
            return false;
        }
        try {
            $record = self::find($token);
            if (!$record) {
                return false;
            }
            $transaction = $DB->start_delegated_transaction();
            $record = $DB->get_record('local_qlogin_shomokh_reset', ['id' => $record->id], '*', MUST_EXIST);
            $user = $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0, 'suspended' => 0]);
            if (
                !$user || $record->state !== 'pending' || $record->expiresat < time() || !self::eligible_user($user)
                    || !hash_equals($record->tokenhash, $tokenhash)
            ) {
                $transaction->allow_commit();
                return false;
            }
            require_once($CFG->libdir . '/moodlelib.php');
            update_internal_user_password($user, $password);
            $record->state = 'used';
            $record->tokenhash = hash('sha256', random_bytes(32));
            $record->timemodified = time();
            $DB->update_record('local_qlogin_shomokh_reset', $record);
            $transaction->allow_commit();
            \core\session\manager::kill_user_sessions((int)$user->id);
            return true;
        } finally {
            $lock->release();
        }
    }

    /** Sends and increments the request attempt count. */
    private static function send_record(
        \stdClass $record,
        \stdClass $user,
        string $email,
        string $token
    ): \local_qlogin_shomokh\mail\result {
        global $DB;
        $minutes = max(1, (int)ceil(((int)$record->expiresat - time()) / MINSECS));
        $url = new \moodle_url('/local/qlogin_shomokh/reset_email.php', ['token' => $token]);
        $values = [
            'name' => fullname($user),
            'site' => format_string(get_site()->fullname),
            'url' => $url->out(false),
            'minutes' => $minutes,
        ];
        $result = \local_qlogin_shomokh\mail\service::send(new \local_qlogin_shomokh\mail\message(
            (int)$user->id,
            $email,
            get_string('recovery:emailsubject', 'local_qlogin_shomokh', $values),
            get_string('recovery:emailbody', 'local_qlogin_shomokh', $values),
            'recovery',
            'recovery-' . $record->id . '-' . substr($record->tokenhash, 0, 32)
        ));
        $record->attempts = (int)$record->attempts + 1;
        $record->timemodified = time();
        $DB->update_record('local_qlogin_shomokh_reset', $record);
        return $result;
    }

    /** Permits only configured internal authentication accounts. */
    private static function eligible_user(\stdClass $user): bool {
        $types = array_filter(array_map('trim', explode(
            ',',
            (string)get_config('local_qlogin_shomokh', 'authtypes')
        )));
        return in_array((string)$user->auth, $types ?: ['manual'], true);
    }
}
