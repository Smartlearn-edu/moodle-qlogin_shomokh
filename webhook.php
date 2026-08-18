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

define('NO_MOODLE_COOKIES', true);
// A webhook response must never contain developer notices or stack traces.
define('NO_DEBUG_DISPLAY', true);
require_once('../../config.php');

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'GET') {
    $mode = optional_param('hub_mode', '', PARAM_ALPHANUMEXT);
    $token = optional_param('hub_verify_token', '', PARAM_RAW_TRIMMED);
    $challenge = optional_param('hub_challenge', '', PARAM_RAW_TRIMMED);
    $expected = (string)get_config('local_qlogin_shomokh', 'webhookverifytoken');
    if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
        local_qlogin_shomokh_webhook_response(200, $challenge);
    }
    local_qlogin_shomokh_webhook_response(403, 'Forbidden');
}
if ($method !== 'POST') {
    local_qlogin_shomokh_webhook_response(405, 'Method not allowed');
}
$dbman = $DB->get_manager();
if (
    !$dbman->table_exists('local_qlogin_shomokh_event')
        || !$dbman->table_exists('local_qlogin_shomokh_verify')
        || !$dbman->table_exists('local_qlogin_shomokh_recov')
) {
    local_qlogin_shomokh_webhook_response(503, 'Service unavailable');
}

$rawbody = file_get_contents('php://input');
if ($rawbody === false || strlen($rawbody) > 1048576) {
    local_qlogin_shomokh_webhook_response(413, 'Payload too large');
}
$signature = clean_param($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '', PARAM_RAW_TRIMMED);
$secret = (string)get_config('local_qlogin_shomokh', 'webhookappsecret');
$expected = 'sha256=' . hash_hmac('sha256', $rawbody, $secret);
if ($secret === '' || !hash_equals($expected, $signature)) {
    local_qlogin_shomokh_webhook_response(401, 'Invalid signature');
}
$payload = json_decode($rawbody);
if (!is_object($payload) || json_last_error() !== JSON_ERROR_NONE) {
    local_qlogin_shomokh_webhook_response(400, 'Invalid JSON');
}

foreach ((array)($payload->entry ?? []) as $entry) {
    foreach ((array)($entry->changes ?? []) as $change) {
        $value = is_object($change->value ?? null) ? $change->value : null;
        $configuredid = (string)get_config('local_qlogin_shomokh', 'businessphonenumberid');
        $receivedid = (string)($value->metadata->phone_number_id ?? '');
        if ($configuredid === '' || !hash_equals($configuredid, $receivedid)) {
            // Return 200 to Meta to avoid retries, but retain a privacy-minimal
            // diagnostic so administrators can see a wrong Phone Number ID.
            foreach ((array)($value->messages ?? []) as $message) {
                $messageid = substr(clean_param($message->id ?? '', PARAM_RAW_TRIMMED), 0, 255);
                if ($messageid !== '') {
                    \local_qlogin_shomokh\verification::record_event(
                        $messageid,
                        'configuration',
                        ['status' => 'wrongphoneid', 'userid' => null]
                    );
                }
            }
            continue;
        }
        foreach ((array)($value->messages ?? []) as $message) {
            $messageid = substr(clean_param($message->id ?? '', PARAM_RAW_TRIMMED), 0, 255);
            if ($messageid !== '' && $DB->record_exists('local_qlogin_shomokh_event', ['messageid' => $messageid])) {
                continue;
            }
            if (
                $messageid === '' || ($message->type ?? '') !== 'text'
                    || empty($message->from) || empty($message->text->body)
            ) {
                if ($messageid !== '') {
                    \local_qlogin_shomokh\verification::record_event(
                        $messageid,
                        'unsupported',
                        ['status' => 'unsupportedmessage', 'userid' => null]
                    );
                }
                continue;
            }
            $body = (string)$message->text->body;
            if (preg_match('/\bSHOMOKH\s+TEST\b/i', $body)) {
                $result = \local_qlogin_shomokh\whatsapp_test::verify((string)$message->from, $body);
                $eventtype = 'integrationtest';
            } else if (preg_match('/\bSHOMOKH\s+RESET\b/i', $body)) {
                $result = \local_qlogin_shomokh\recovery::verify_from_whatsapp((string)$message->from, $body);
                $eventtype = 'recovery';
            } else {
                $result = \local_qlogin_shomokh\verification::verify_from_whatsapp((string)$message->from, $body);
                $eventtype = 'verification';
            }
            \local_qlogin_shomokh\verification::record_event($messageid, $eventtype, $result);
        }
    }
}
local_qlogin_shomokh_webhook_response(200, 'OK');

/**
 * Sends a minimal webhook response.
 */
function local_qlogin_shomokh_webhook_response(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}
