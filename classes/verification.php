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

/** Unified verification service. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Coordinates email and WhatsApp verification using one state model. */
final class verification {
    public const EMAIL = 'email';
    public const PHONE = 'phone';
    public const PENDING = 'pending';
    public const VERIFIED = 'verified';
    public const EXPIRED = 'expired';
    public const WAIVED = 'waived';

    /** Whether the plugin and its upgraded schema are ready. */
    public static function available(): bool {
        global $DB;
        return (bool)get_config('local_qlogin_shomokh', 'enabled')
            && $DB->get_manager()->table_exists('local_qlogin_shomokh_verify');
    }

    /** Configured verification channels. */
    public static function required_channels(): array {
        $channels = [];
        if ((bool)get_config('local_qlogin_shomokh', 'requireemail')) {
            $channels[] = self::EMAIL;
        }
        if ((bool)get_config('local_qlogin_shomokh', 'requirephone')) {
            $channels[] = self::PHONE;
        }
        return $channels;
    }

    /** Whether a Moodle account belongs to this flow. */
    public static function eligible(\stdClass $user): bool {
        global $DB;
        if (!self::available() || empty($user->id) || is_siteadmin($user->id) || !empty($user->deleted)) {
            return false;
        }
        $types = array_filter(array_map('trim', explode(',', (string)get_config('local_qlogin_shomokh', 'authtypes'))));
        return in_array((string)$user->auth, $types ?: ['manual'], true)
            && (migration::phone_for_user($user) !== ''
                || $DB->record_exists('local_qlogin_shomokh_verify', [
                    'userid' => $user->id,
                    'channel' => self::PHONE,
                ]));
    }

    /** Creates or synchronises all required channel records. */
    public static function ensure_for_user(\stdClass $user): array {
        $records = [];
        if (!self::eligible($user)) {
            return $records;
        }
        foreach (self::required_channels() as $channel) {
            $records[$channel] = self::ensure_channel($user, $channel);
        }
        return $records;
    }

    /** Creates or synchronises one channel record. */
    public static function ensure_channel(\stdClass $user, string $channel): \stdClass {
        global $DB;
        if (!in_array($channel, [self::EMAIL, self::PHONE], true)) {
            throw new \coding_exception('Unsupported verification channel');
        }
        $target = $channel === self::EMAIL ? manager::normalise_email((string)$user->email)
            : migration::phone_for_user($user);
        $record = $DB->get_record('local_qlogin_shomokh_verify', ['userid' => $user->id, 'channel' => $channel]);
        $now = time();
        if (!$record) {
            $record = (object)[
                'userid' => $user->id, 'channel' => $channel, 'target' => $target, 'tokenhash' => '',
                'state' => self::PENDING, 'expiresat' => $now + self::grace_seconds(), 'verifiedat' => null,
                'lastsentat' => 0, 'remindercount' => 0, 'lastremindedat' => 0,
                'timecreated' => $now, 'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('local_qlogin_shomokh_verify', $record);
            return $record;
        }
        if ($record->target !== $target) {
            $previousstate = (string)$record->state;
            $record->target = $target;
            $record->tokenhash = '';
            $record->verifiedat = null;
            $record->lastsentat = 0;
            $record->remindercount = 0;
            $record->lastremindedat = 0;
            if ($previousstate === self::VERIFIED) {
                $record->state = self::PENDING;
                $record->expiresat = $now + self::grace_seconds();
                $record->timecreated = $now;
            } else if ($previousstate === self::WAIVED) {
                $record->state = self::WAIVED;
            } else {
                // Changing an unverified contact must not restart its grace period.
                $record->state = $previousstate;
                if (empty($record->expiresat)) {
                    $record->expiresat = (int)$record->timecreated + self::grace_seconds();
                }
            }
            $record->timemodified = $now;
            $DB->update_record('local_qlogin_shomokh_verify', $record);
        }
        return $record;
    }

    /**
     * Starts tracking an existing pre-plugin account on its first successful login.
     *
     * A completely untracked account is treated as legacy: its valid existing
     * email is trusted, while phone verification starts immediately. Accounts
     * created by this plugin already have records and are never auto-trusted.
     */
    public static function bootstrap_existing_user(\stdClass $user): array {
        global $DB;
        if (
            !self::available() || empty($user->id) || is_siteadmin($user->id)
                || !empty($user->deleted) || !empty($user->suspended)
        ) {
            return [];
        }
        $types = array_filter(array_map('trim', explode(
            ',',
            (string)get_config('local_qlogin_shomokh', 'authtypes')
        )));
        if (!in_array((string)$user->auth, $types ?: ['manual'], true)) {
            return [];
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_qlogin_shomokh');
        $lock = $lockfactory->get_lock('bootstrap_user_' . (int)$user->id, 5);
        if (!$lock) {
            $records = [];
            foreach ($DB->get_records('local_qlogin_shomokh_verify', ['userid' => $user->id]) as $record) {
                $records[(string)$record->channel] = $record;
            }
            return $records;
        }
        try {
            $records = [];
            foreach ($DB->get_records('local_qlogin_shomokh_verify', ['userid' => $user->id]) as $record) {
                $records[(string)$record->channel] = $record;
            }
            $untracked = empty($records);
            if ($untracked) {
                foreach (self::required_channels() as $channel) {
                    $record = self::ensure_channel($user, $channel);
                    if (
                        $channel === self::EMAIL
                            && manager::normalise_email((string)$user->email) !== ''
                    ) {
                        self::mark_verified($record);
                    }
                }
            } else if (
                in_array(self::PHONE, self::required_channels(), true)
                    && !isset($records[self::PHONE])
            ) {
                $emailrecord = $records[self::EMAIL] ?? null;
                if (!$emailrecord || self::record_complete($emailrecord)) {
                    self::ensure_channel($user, self::PHONE);
                }
            }
            $records = [];
            foreach ($DB->get_records('local_qlogin_shomokh_verify', ['userid' => $user->id]) as $record) {
                $records[(string)$record->channel] = $record;
            }
            return $records;
        } finally {
            $lock->release();
        }
    }

    /** Tracks a newly created account without trusting either contact channel. */
    public static function track_new_user(\stdClass $user): array {
        if (
            !self::available() || empty($user->id) || is_siteadmin($user->id)
                || !empty($user->deleted) || !empty($user->suspended)
        ) {
            return [];
        }
        $types = array_filter(array_map('trim', explode(
            ',',
            (string)get_config('local_qlogin_shomokh', 'authtypes')
        )));
        if (!in_array((string)$user->auth, $types ?: ['manual'], true)) {
            return [];
        }
        $records = [];
        foreach (self::required_channels() as $channel) {
            $records[$channel] = self::ensure_channel($user, $channel);
        }
        return $records;
    }

    /** Explicitly trusts one legacy email during an administrator-reviewed migration. */
    public static function trust_legacy_email(\stdClass $user): bool {
        if (!self::available() || manager::normalise_email((string)$user->email) === '') {
            return false;
        }
        $record = self::ensure_channel($user, self::EMAIL);
        if (self::record_complete($record)) {
            return false;
        }
        self::mark_verified($record);
        return true;
    }

    /** Gets one user's channel record. */
    public static function get(int $userid, string $channel) {
        global $DB;
        return $DB->get_record('local_qlogin_shomokh_verify', ['userid' => $userid, 'channel' => $channel]);
    }

    /** Whether a channel is satisfied by verification or an explicit exemption. */
    public static function channel_complete(int $userid, string $channel): bool {
        $record = self::get($userid, $channel);
        return $record ? self::record_complete($record) : self::is_exempt($userid, $channel);
    }

    /** Whether a loaded record is complete, avoiding a duplicate database lookup. */
    public static function record_complete(\stdClass $record): bool {
        return in_array($record->state, [self::VERIFIED, self::WAIVED], true)
            || self::is_exempt((int)$record->userid, (string)$record->channel);
    }

    /** Whether all currently required channels are complete. */
    public static function all_complete(int $userid): bool {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user || !self::eligible($user)) {
            return true;
        }
        foreach (self::required_channels() as $channel) {
            if (!self::channel_complete($userid, $channel)) {
                return false;
            }
        }
        return true;
    }

    /** Whether this user is currently past the deadline for any required channel. */
    public static function requires_enforcement(int $userid): bool {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user || !self::eligible($user)) {
            return false;
        }
        foreach (self::required_channels() as $channel) {
            $record = self::get($userid, $channel);
            if (
                $record && !self::record_complete($record)
                    && ($record->state === self::EXPIRED || (int)$record->expiresat < time())
            ) {
                return true;
            }
        }
        return false;
    }

    /** Sends a fresh email verification link immediately and queues only transient failures. */
    public static function issue_email(\stdClass $user, bool $cooldown = true): \stdClass {
        global $DB;
        $record = self::ensure_channel($user, self::EMAIL);
        if (self::channel_complete((int)$user->id, self::EMAIL)) {
            return $record;
        }
        if (manager::normalise_email($record->target) === '') {
            throw new \moodle_exception('error:email', 'local_qlogin_shomokh');
        }
        self::check_cooldown($record, $cooldown);
        $record->lastsentat = time();
        $token = \local_qlogin_shomokh\mail\config::derive_token(
            'verification',
            (int)$record->id,
            (int)$user->id,
            (int)$record->lastsentat
        );
        $record->tokenhash = manager::hash_token($token);
        $record->state = self::PENDING;
        $record->timemodified = time();
        $DB->update_record('local_qlogin_shomokh_verify', $record);
        $result = self::send_email_record($record, $user, $token);
        if (!$result->accepted) {
            $record->mailstatus = $result->retryable
                && \local_qlogin_shomokh\mail\service::queue_retry('verification', (int)$record->id, 1)
                ? 'queued' : 'failed';
            if ($record->mailstatus === 'failed') {
                throw new \moodle_exception($result->status === 'configerror'
                    ? 'mail:notconfigured' : 'mail:sendfailed', 'local_qlogin_shomokh');
            }
        } else {
            $record->mailstatus = 'sent';
        }
        return $record;
    }

    /** Retries the current verification link without exposing its raw token in task data. */
    public static function retry_email(int $recordid, int $attempt): void {
        global $DB;
        $record = $DB->get_record('local_qlogin_shomokh_verify', [
            'id' => $recordid,
            'channel' => self::EMAIL,
        ]);
        $user = $record ? $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0]) : false;
        if (!$record || !$user || self::record_complete($record) || $record->tokenhash === '') {
            return;
        }
        $token = \local_qlogin_shomokh\mail\config::derive_token(
            'verification',
            (int)$record->id,
            (int)$user->id,
            (int)$record->lastsentat
        );
        if (!hash_equals($record->tokenhash, manager::hash_token($token))) {
            // Upgrade legacy queued mail to the deterministic, retry-safe format.
            self::issue_email($user, false);
            return;
        }
        $result = self::send_email_record($record, $user, $token);
        if (!$result->accepted && $result->retryable) {
            \local_qlogin_shomokh\mail\service::queue_retry('verification', $recordid, $attempt);
        }
    }

    /** Builds and sends one verification message through the selected provider. */
    private static function send_email_record(\stdClass $record, \stdClass $user, string $token): \local_qlogin_shomokh\mail\result {
        $url = new \moodle_url('/local/qlogin_shomokh/verify_email.php', ['u' => $user->id, 'token' => $token]);
        $values = [
            'name' => fullname($user),
            'site' => format_string(get_site()->fullname),
            'url' => $url->out(false),
            'days' => max(1, (int)ceil(((int)$record->expiresat - time()) / DAYSECS)),
        ];
        $key = 'verification-' . $record->id . '-' . substr($record->tokenhash, 0, 32);
        return \local_qlogin_shomokh\mail\service::send(new \local_qlogin_shomokh\mail\message(
            (int)$user->id,
            (string)$record->target,
            get_string('verificationemail:subject', 'local_qlogin_shomokh', $values),
            get_string('verificationemail:body', 'local_qlogin_shomokh', $values),
            'verification',
            $key
        ));
    }

    /** Confirms an email link. */
    public static function verify_email(int $userid, string $token): bool {
        global $DB;
        $record = self::get($userid, self::EMAIL);
        if (!$record || $record->state === self::VERIFIED || $record->tokenhash === '') {
            return (bool)$record && $record->state === self::VERIFIED;
        }
        if (!hash_equals($record->tokenhash, manager::hash_token($token))) {
            return false;
        }
        self::mark_verified($record);
        enforcement::release_if_complete($userid);
        return true;
    }

    /** Issues a WhatsApp code and returns it for the current browser session. */
    public static function issue_phone(\stdClass $user, bool $cooldown = true): array {
        global $DB, $SESSION;
        $record = self::ensure_channel($user, self::PHONE);
        if (manager::normalise_phone((string)$record->target) === '') {
            throw new \moodle_exception('claim:phonerequired', 'local_qlogin_shomokh');
        }
        if (self::channel_complete((int)$user->id, self::PHONE)) {
            return [$record, ''];
        }
        self::check_cooldown($record, $cooldown);
        $code = manager::generate_code();
        $record->tokenhash = manager::hash_token($code);
        $record->state = self::PENDING;
        $record->lastsentat = time();
        $record->timemodified = time();
        $DB->update_record('local_qlogin_shomokh_verify', $record);
        if (empty($SESSION->local_qlogin_shomokh_codes)) {
            $SESSION->local_qlogin_shomokh_codes = [];
        }
        $SESSION->local_qlogin_shomokh_codes[$user->id] = $code;
        return [$record, $code];
    }

    /** Returns an existing in-session code or issues a new one. */
    public static function phone_code(\stdClass $user): array {
        global $SESSION;
        $record = self::ensure_channel($user, self::PHONE);
        $code = $SESSION->local_qlogin_shomokh_codes[$user->id] ?? '';
        if ($code !== '' && hash_equals($record->tokenhash, manager::hash_token($code))) {
            return [$record, $code];
        }
        if ($record->tokenhash === '') {
            return self::issue_phone($user, false);
        }
        return [$record, ''];
    }

    /** Builds the wa.me URL for a phone code. */
    public static function whatsapp_url(string $code, string $purpose = 'VERIFY'): ?string {
        $number = manager::normalise_phone((string)get_config('local_qlogin_shomokh', 'businessnumber'));
        if ($number === '') {
            return null;
        }
        $message = 'SHOMOKH ' . $purpose . ' ' . strtoupper($code);
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }

    /** Verifies an authenticated inbound WhatsApp message. */
    public static function verify_from_whatsapp(string $fromphone, string $message): array {
        global $DB;
        if (!self::available()) {
            return ['status' => 'verificationdisabled', 'userid' => null];
        }
        $phone = manager::normalise_phone($fromphone);
        if ($phone === '') {
            return ['status' => 'unmatchedphone', 'userid' => null];
        }
        if (!preg_match('/\bSHOMOKH\s+VERIFY\s+([A-HJ-NP-Z2-9]{10})\b/i', trim($message), $matches)) {
            return ['status' => 'invalidcode', 'userid' => null];
        }
        $records = $DB->get_records('local_qlogin_shomokh_verify', [
            'channel' => self::PHONE,
            'target' => $phone,
        ], 'id ASC');
        if (!$records) {
            return ['status' => 'unmatchedphone', 'userid' => null];
        }

        // Legacy data can contain more than one verification row for one
        // phone. Match the secret first; selecting the first phone row could
        // incorrectly stop at an older verified account.
        $codehash = manager::hash_token(strtoupper($matches[1]));
        $matching = [];
        $pending = [];
        $verified = [];
        foreach ($records as $record) {
            if ($record->state === self::VERIFIED) {
                $verified[] = $record;
                continue;
            }
            $pending[] = $record;
            if ($record->tokenhash !== '' && hash_equals($record->tokenhash, $codehash)) {
                $matching[] = $record;
            }
        }
        if (count($matching) === 1) {
            $record = reset($matching);
            self::mark_verified($record);
            enforcement::release_if_complete((int)$record->userid);
            return ['status' => 'verified', 'userid' => $record->userid];
        }
        if (count($matching) > 1) {
            return ['status' => 'ambiguouscode', 'userid' => null];
        }
        if (!$pending && $verified) {
            return ['status' => 'alreadyverified', 'userid' => reset($verified)->userid];
        }
        return [
            'status' => 'invalidcode',
            'userid' => count($pending) === 1 ? reset($pending)->userid : null,
        ];
    }

    /** Deduplicates and stores a minimal webhook result. */
    public static function record_event(string $messageid, string $eventtype, array $result): bool {
        global $DB;
        $messageid = substr(clean_param($messageid, PARAM_RAW_TRIMMED), 0, 255);
        if ($messageid === '' || $DB->record_exists('local_qlogin_shomokh_event', ['messageid' => $messageid])) {
            return false;
        }
        try {
            $DB->insert_record('local_qlogin_shomokh_event', (object)[
                'userid' => $result['userid'] ?: null, 'messageid' => $messageid,
                'eventtype' => clean_param($eventtype, PARAM_ALPHANUMEXT),
                'status' => clean_param($result['status'], PARAM_ALPHANUMEXT), 'timecreated' => time(),
            ]);
            return true;
        } catch (\dml_write_exception $exception) {
            return false;
        }
    }

    /** Whether an exemption applies directly or through cohort membership. */
    public static function is_exempt(int $userid, string $channel): bool {
        global $DB;
        [$channelsql, $channelparams] = $DB->get_in_or_equal([$channel, 'all'], SQL_PARAMS_NAMED, 'exchannel');
        $params = ['userscope' => 'user', 'userid' => $userid] + $channelparams;
        if (
            $DB->record_exists_select(
                'local_qlogin_shomokh_exempt',
                "scope = :userscope AND scopeid = :userid AND channel $channelsql",
                $params
            )
        ) {
            return true;
        }
        $sql = "SELECT 1
                  FROM {local_qlogin_shomokh_exempt} e
                  JOIN {cohort_members} cm ON cm.cohortid = e.scopeid
                 WHERE e.scope = :cohortscope AND cm.userid = :userid AND e.channel $channelsql";
        return $DB->record_exists_sql(
            $sql,
            ['cohortscope' => 'cohort', 'userid' => $userid] + $channelparams
        );
    }

    /** Adds or updates an exemption. */
    public static function set_exemption(string $scope, int $scopeid, string $channel, string $reason, int $createdby): void {
        global $DB;
        if (!in_array($scope, ['user', 'cohort'], true) || !in_array($channel, ['all', self::EMAIL, self::PHONE], true)) {
            throw new \moodle_exception('invalidaction', 'local_qlogin_shomokh');
        }
        $existing = $DB->get_record('local_qlogin_shomokh_exempt', compact('scope', 'scopeid', 'channel'));
        $record = (object)[
            'scope' => $scope, 'scopeid' => $scopeid, 'channel' => $channel,
            'reason' => clean_param($reason, PARAM_TEXT), 'createdby' => $createdby, 'timecreated' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_qlogin_shomokh_exempt', $record);
        } else {
            $DB->insert_record('local_qlogin_shomokh_exempt', $record);
        }
    }

    /** Removes one exemption. */
    public static function delete_exemption(int $id): void {
        global $DB;
        $DB->delete_records('local_qlogin_shomokh_exempt', ['id' => $id]);
    }

    /** Marks pending records expired and returns affected user IDs. */
    public static function expire_due(): array {
        global $DB;
        $now = time();
        $grace = self::grace_seconds();
        foreach (
            $DB->get_records_select(
                'local_qlogin_shomokh_verify',
                'state = :pending OR state = :expired',
                ['pending' => self::PENDING, 'expired' => self::EXPIRED]
            ) as $record
        ) {
            $deadline = (int)$record->timecreated + $grace;
            $state = $record->state === self::EXPIRED && $deadline >= $now ? self::PENDING : $record->state;
            if ((int)$record->expiresat !== $deadline || $state !== $record->state) {
                $record->expiresat = $deadline;
                $record->state = $state;
                $record->timemodified = $now;
                $DB->update_record('local_qlogin_shomokh_verify', $record);
            }
        }
        $records = $DB->get_records_select(
            'local_qlogin_shomokh_verify',
            'state = :state AND expiresat > 0 AND expiresat < :now',
            ['state' => self::PENDING, 'now' => $now]
        );
        $users = [];
        foreach ($records as $record) {
            $record->state = self::EXPIRED;
            $record->timemodified = $now;
            $DB->update_record('local_qlogin_shomokh_verify', $record);
            $users[(int)$record->userid] = true;
        }
        return array_keys($users);
    }

    /** Sends a reminder when its interval and configured maximum allow it. */
    public static function remind(\stdClass $record): bool {
        global $DB;
        $max = max(0, min(50, (int)get_config('local_qlogin_shomokh', 'maxreminders')));
        $interval = max(1, min(90, (int)get_config('local_qlogin_shomokh', 'reminderintervaldays'))) * DAYSECS;
        if ($record->remindercount >= $max || ($record->lastremindedat && time() - $record->lastremindedat < $interval)) {
            return false;
        }
        $user = $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0]);
        if (!$user) {
            return false;
        }
        $result = self::send_reminder($record, $user);
        if (!$result->accepted) {
            if ($result->retryable) {
                \local_qlogin_shomokh\mail\service::queue_retry('reminder', (int)$record->id, 1);
            }
            return false;
        }
        $record->remindercount++;
        $record->lastremindedat = time();
        $record->timemodified = time();
        $DB->update_record('local_qlogin_shomokh_verify', $record);
        return true;
    }

    /** Retries a reminder using the same idempotency key until it is accepted. */
    public static function retry_reminder(int $recordid, int $attempt): void {
        global $DB;
        $record = $DB->get_record('local_qlogin_shomokh_verify', ['id' => $recordid]);
        $user = $record ? $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0]) : false;
        if (!$record || !$user || self::record_complete($record)) {
            return;
        }
        $result = self::send_reminder($record, $user);
        if ($result->accepted) {
            $record->remindercount++;
            $record->lastremindedat = time();
            $record->timemodified = time();
            $DB->update_record('local_qlogin_shomokh_verify', $record);
        } else if ($result->retryable) {
            \local_qlogin_shomokh\mail\service::queue_retry('reminder', $recordid, $attempt);
        }
    }

    /** Builds one non-sensitive reminder email. */
    private static function send_reminder(\stdClass $record, \stdClass $user): \local_qlogin_shomokh\mail\result {
        $url = new \moodle_url('/local/qlogin_shomokh/verify.php');
        $body = get_string('reminder:body', 'local_qlogin_shomokh', [
            'name' => fullname($user),
            'url' => $url->out(false),
        ]);
        $key = 'reminder-' . $record->id . '-' . ((int)$record->remindercount + 1);
        return \local_qlogin_shomokh\mail\service::send(new \local_qlogin_shomokh\mail\message(
            (int)$user->id,
            (string)$user->email,
            get_string('reminder:subject', 'local_qlogin_shomokh'),
            $body,
            'reminder',
            $key
        ));
    }

    /** Configured grace period in seconds; default 30 days. */
    public static function grace_seconds(): int {
        $configured = get_config('local_qlogin_shomokh', 'graceperioddays');
        $days = $configured === false ? 30 : (int)$configured;
        return max(1, min(365, $days)) * DAYSECS;
    }

    /** Sets a record verified. */
    private static function mark_verified(\stdClass $record): void {
        global $DB;
        $record->state = self::VERIFIED;
        $record->verifiedat = time();
        $record->tokenhash = '';
        $record->timemodified = time();
        $DB->update_record('local_qlogin_shomokh_verify', $record);
        if ((string)$record->channel === self::PHONE) {
            migration::mark_alias_verified((int)$record->userid, (string)$record->target);
        }
    }

    /** Seconds remaining before this channel can send again. */
    public static function cooldown_remaining(\stdClass $record): int {
        $seconds = max(0, min(DAYSECS, (int)get_config('local_qlogin_shomokh', 'resendcooldown')));
        if (empty($record->lastsentat)) {
            return 0;
        }
        return max(0, $seconds - (time() - (int)$record->lastsentat));
    }

    /** Enforces a configurable resend interval for this record and channel only. */
    private static function check_cooldown(\stdClass $record, bool $enforce): void {
        if (!$enforce) {
            return;
        }
        $remaining = self::cooldown_remaining($record);
        if ($remaining > 0) {
            throw new \moodle_exception(
                'waitbeforeresend',
                'local_qlogin_shomokh',
                '',
                $remaining
            );
        }
    }
}
