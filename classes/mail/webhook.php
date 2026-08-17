<?php
// This file is part of Moodle - https://moodle.org/

/** Resend webhook verification. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\mail;

defined('MOODLE_INTERNAL') || die();

/** Verifies Svix signatures and applies privacy-minimal delivery states. */
final class webhook {
    /** Returns a verified payload or false. */
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

    /** Updates a known provider message without storing the webhook payload. */
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
