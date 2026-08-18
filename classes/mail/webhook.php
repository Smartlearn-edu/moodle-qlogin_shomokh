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
 * Webhook functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh\mail;

/**
 * Verifies Svix signatures and applies privacy-minimal delivery states.
 */
final class webhook {
    /**
     * Returns a verified payload or false.
     */
    public static function verify(string $rawbody, string $id, string $timestamp, string $signature) {
        [$secret] = config::resend_webhook_secret();
        if ($secret === '' || $id === '' || !ctype_digit($timestamp) || $signature === '') {
            return false;
        }
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }
        $encodedsecret = strpos($secret, 'whsec_') === 0 ? substr($secret, 6) : $secret;
        $key = base64_decode($encodedsecret, true);
        if ($key === false) {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $rawbody, $key, true));
        $valid = false;
        foreach (preg_split('/\s+/', trim($signature)) as $candidate) {
            [$version, $value] = array_pad(explode(',', $candidate, 2), 2, '');
            if ($version === 'v1' && $value !== '' && hash_equals($expected, $value)) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            return false;
        }
        $payload = json_decode($rawbody);
        return is_object($payload) && json_last_error() === JSON_ERROR_NONE ? $payload : false;
    }

    /**
     * Updates a known provider message without storing the webhook payload.
     */
    public static function apply(\stdClass $payload): bool {
        global $DB;
        $type = clean_param((string)($payload->type ?? ''), PARAM_ALPHANUMEXT);
        $messageid = clean_param((string)($payload->data->email_id ?? $payload->data->id ?? ''), PARAM_RAW_TRIMMED);
        if ($messageid === '' || strpos($type, 'email.') !== 0) {
            return false;
        }
        $map = [
            'email.sent' => 'sent',
            'email.delivered' => 'delivered',
            'email.delivery_delayed' => 'delayed',
            'email.bounced' => 'bounced',
            'email.complained' => 'complained',
            'email.failed' => 'failed',
            'email.suppressed' => 'suppressed',
        ];
        if (!isset($map[$type])) {
            return false;
        }
        $record = $DB->get_record('local_qlogin_shomokh_mail', ['messageid' => $messageid]);
        if (!$record) {
            return false;
        }
        $record->status = $map[$type];
        $record->timemodified = time();
        $DB->update_record('local_qlogin_shomokh_mail', $record);
        return true;
    }
}
