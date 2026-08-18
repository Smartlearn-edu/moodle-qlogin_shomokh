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
 * Whatsapp test test functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Unit tests for the administrator-only WhatsApp integration challenge.
 */
final class whatsapp_test_test extends \advanced_testcase {
    public function test_valid_challenge_passes_once_without_storing_raw_code(): void {
        global $SESSION;

        $this->resetAfterTest();
        $SESSION->local_qlogin_shomokh_whatsapptest = [];
        $code = whatsapp_test::issue(2);

        $this->assertNotSame($code, get_config('local_qlogin_shomokh', 'whatsapptesthash'));
        $this->assertSame($code, whatsapp_test::active_code(2));
        $this->assertSame(
            ['status' => 'passed', 'userid' => 2],
            whatsapp_test::verify('966500000000', 'SHOMOKH TEST ' . $code)
        );
        $this->assertSame('', whatsapp_test::active_code(2));
        $this->assertSame(
            ['status' => 'expired', 'userid' => null],
            whatsapp_test::verify('966500000000', 'SHOMOKH TEST ' . $code)
        );
    }

    public function test_invalid_code_does_not_consume_active_challenge(): void {
        global $SESSION;

        $this->resetAfterTest();
        $SESSION->local_qlogin_shomokh_whatsapptest = [];
        $code = whatsapp_test::issue(3);

        $this->assertSame(
            ['status' => 'invalidcode', 'userid' => 3],
            whatsapp_test::verify('966500000000', 'SHOMOKH TEST AAAAA22222')
        );
        $this->assertSame($code, whatsapp_test::active_code(3));
    }
}
