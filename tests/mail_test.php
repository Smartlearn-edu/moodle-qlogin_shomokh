<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Tests deterministic link tokens and signed Resend webhook validation. */
final class mail_test extends \advanced_testcase {
    public function test_retry_token_is_stable_and_purpose_specific(): void {
        $this->resetAfterTest();
        set_config('tokensecret', str_repeat('a', 64), 'local_qlogin_shomokh');
        $first = mail\config::derive_token('verification', 10, 20, 30);
        $this->assertSame($first, mail\config::derive_token('verification', 10, 20, 30));
        $this->assertNotSame($first, mail\config::derive_token('recovery', 10, 20, 30));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
    }

    public function test_resend_webhook_rejects_modified_payload(): void {
        $this->resetAfterTest();
        $secretbytes = 'unit-test-signing-secret';
        set_config('resendwebhooksecret', 'whsec_' . base64_encode($secretbytes), 'local_qlogin_shomokh');
        $payload = '{"type":"email.delivered","data":{"email_id":"email_123"}}';
        $id = 'msg_test';
        $timestamp = (string)time();
        $signature = 'v1,' . base64_encode(hash_hmac('sha256',
            $id . '.' . $timestamp . '.' . $payload, $secretbytes, true));
        $this->assertIsObject(mail\webhook::verify($payload, $id, $timestamp, $signature));
        $this->assertFalse(mail\webhook::verify($payload . ' ', $id, $timestamp, $signature));
    }

    public function test_resend_requires_both_api_key_and_valid_sender_address(): void {
        $this->resetAfterTest();
        set_config('resendapikey', 're_test_only', 'local_qlogin_shomokh');
        set_config('resendfromemail', '', 'local_qlogin_shomokh');
        $this->assertFalse(mail\config::resend_ready());

        set_config('resendfromemail', 'noreply@example.com', 'local_qlogin_shomokh');
        $this->assertTrue(mail\config::resend_ready());
    }
}
