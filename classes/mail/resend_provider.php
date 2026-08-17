<?php
// This file is part of Moodle - https://moodle.org/

/** Resend API provider. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\mail;

defined('MOODLE_INTERNAL') || die();

/** Sends directly through Resend using Moodle's HTTP client. */
final class resend_provider implements provider_interface {
    public function name(): string {
        return 'resend';
    }

    public function send(message $message): result {
        global $CFG;
        [$apikey] = config::resend_api_key();
        $fromemail = \local_qlogin_shomokh\manager::normalise_email(
            (string)get_config('local_qlogin_shomokh', 'resendfromemail'));
        if ($apikey === '' || $fromemail === '') {
            return new result(false, false, 'configerror', 0, null,
                get_string('mail:notconfigured', 'local_qlogin_shomokh'));
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
            'html' => '<div dir="auto">' . nl2br(htmlspecialchars($message->text,
                ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div>',
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
            $response = $curl->post('https://api.resend.com/emails', json_encode($payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), [
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
