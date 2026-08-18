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
 * Manager test functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Unit tests for security-sensitive phone and token normalisation.
 */
final class manager_test extends \advanced_testcase {
    public function test_normalises_international_numbers_and_arabic_digits(): void {
        $this->assertSame('12025550123', manager::normalise_phone('+1 (202) 555-0123'));
        $this->assertSame('999123456789', manager::normalise_phone('٠٠٩٩٩١٢٣٤٥٦٧٨٩'));
    }

    public function test_rejects_ambiguous_local_or_invalid_numbers(): void {
        $this->assertSame('', manager::normalise_phone('0123456789'));
        $this->assertSame('', manager::normalise_phone('123'));
        $this->assertSame('', manager::normalise_phone('+1234567890123456'));
    }

    public function test_admin_repair_removes_one_accidental_country_code_duplicate(): void {
        $this->assertSame(
            '966534390821',
            manager::remove_repeated_country_code('966966534390821', '966')
        );
        $this->assertSame(
            '201001234567',
            manager::remove_repeated_country_code('20201001234567', '20')
        );
        $this->assertSame(
            '14155550123',
            manager::remove_repeated_country_code('14155550123', '1')
        );
    }

    public function test_normalises_and_validates_email_addresses(): void {
        $this->assertSame('student@example.com', manager::normalise_email(' Student@Example.COM '));
        $this->assertSame('', manager::normalise_email('not-an-email'));
        $this->assertSame('', manager::normalise_email(''));
    }

    public function test_generated_codes_are_unambiguous_and_hashable(): void {
        $code = manager::generate_code();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{10}$/', $code);
        $this->assertSame(64, strlen(manager::hash_token($code)));
    }
}
