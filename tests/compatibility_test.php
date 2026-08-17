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

namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Unit tests for legacy retirement safety checks. */
final class compatibility_test extends \advanced_testcase {
    public function test_unified_whatsapp_readiness_rejects_identifiers_as_display_numbers(): void {
        $this->resetAfterTest();
        set_config('businessnumber', '1234567890123456', 'local_qlogin_shomokh');
        set_config('businessphonenumberid', '1234567890', 'local_qlogin_shomokh');
        set_config('webhookverifytoken', 'test-verify-token', 'local_qlogin_shomokh');
        set_config('webhookappsecret', 'test-app-secret', 'local_qlogin_shomokh');
        $this->assertFalse(compatibility::unified_whatsapp_ready());

        set_config('businessnumber', '+966 55 000 0000', 'local_qlogin_shomokh');
        $this->assertTrue(compatibility::unified_whatsapp_ready());
    }
}
