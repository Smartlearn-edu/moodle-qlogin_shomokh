<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Unit tests for the administrator-only WhatsApp integration challenge. */
final class whatsapp_test_test extends \advanced_testcase {
    public function test_valid_challenge_passes_once_without_storing_raw_code(): void {
        global $SESSION;

        $this->resetAfterTest();
        $SESSION->local_qlogin_shomokh_whatsapptest = [];
        $code = whatsapp_test::issue(2);

        $this->assertNotSame($code, get_config('local_qlogin_shomokh', 'whatsapptesthash'));
        $this->assertSame($code, whatsapp_test::active_code(2));
        $this->assertSame(['status' => 'passed', 'userid' => 2],
            whatsapp_test::verify('966500000000', 'SHOMOKH TEST ' . $code));
        $this->assertSame('', whatsapp_test::active_code(2));
        $this->assertSame(['status' => 'expired', 'userid' => null],
            whatsapp_test::verify('966500000000', 'SHOMOKH TEST ' . $code));
    }

    public function test_invalid_code_does_not_consume_active_challenge(): void {
        global $SESSION;

        $this->resetAfterTest();
        $SESSION->local_qlogin_shomokh_whatsapptest = [];
        $code = whatsapp_test::issue(3);

        $this->assertSame(['status' => 'invalidcode', 'userid' => 3],
            whatsapp_test::verify('966500000000', 'SHOMOKH TEST AAAAA22222'));
        $this->assertSame($code, whatsapp_test::active_code(3));
    }
}
