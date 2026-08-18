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
 * Resend provider functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh\mail;

/**
 * Sends directly through Resend using Moodle's HTTP client.
 */
final class resend_provider implements provider_interface {
    /**
     * Name method.
     */
    public function name(): string {
        return 'resend';
    }

    /**
     * Send method.
     */
    public function send(message $message): result {
        global $CFG;
        [$apikey] = config::resend_api_key();
        $fromemail = \local_qlogin_shomokh\manager::normalise_email(
            (string)get_config('local_qlogin_shomokh', 'resendfromemail')
        );
        if ($apikey === '' || $fromemail === '') {
            return new result(
                false,
                false,
                'configerror',
                0,
                null,
                get_string('mail:notconfigured', 'local_qlogin_shomokh')
            );
        }
        require_once($CFG->libdir . '/filelib.php');
        $fromname = trim(clean_param((string)get_config('local_qlogin_shomokh', 'resendfromname'), PARAM_TEXT));
        $fromname = str_replace(['<', '>', "\r", "\n"], '', $fromname);
        $from = $fromname === '' ? $fromemail : $fromname . ' <' . $fromemail . '>';
        $payload = [
            'from' => $from,
            'to' => [$message->to],
            'subject' => $message->subject,
            'text' => $message->text,
            'html' => '<div dir="auto">' . nl2br(htmlspecialchars(
                $message->text,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )) . '</div>',
        ];
        $timeout = max(3, min(30, (int)get_config('local_qlogin_shomokh', 'resendtimeout')));
        try {
            $curl = new \curl();
            $curl->setHeader([
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Idempotency-Key: ' . $message->idempotencykey,
                'User-Agent: local_qlogin_shomokh/0.4.2',
            ]);
            $response = $curl->post('https://api.resend.com/emails', json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ), [
                    'CURLOPT_TIMEOUT' => $timeout,
                    'CURLOPT_CONNECTTIMEOUT' => min(5, $timeout),
                ]);
            $info = $curl->get_info();
            $httpstatus = (int)($info['http_code'] ?? 0);
            $decoded = json_decode((string)$response);
            if ($httpstatus >= 200 && $httpstatus < 300 && is_object($decoded) && !empty($decoded->id)) {
                return new result(true, false, 'accepted', $httpstatus, (string)$decoded->id);
            }
            $errorname = is_object($decoded) ? clean_param((string)($decoded->name ?? ''), PARAM_ALPHANUMEXT) : '';
            $retryable = $httpstatus === 0 || $httpstatus === 408 || $httpstatus === 425 || $httpstatus === 429
                || $httpstatus >= 500 || ($httpstatus === 409 && $errorname === 'concurrent_idempotent_requests');
            $safecode = $errorname !== '' ? $errorname : 'http_' . $httpstatus;
            return new result(false, $retryable, $retryable ? 'retry' : 'failed', $httpstatus, null, $safecode);
        } catch (\Throwable $exception) {
            return new result(false, true, 'retry', 0, null, get_class($exception));
        }
    }
}
