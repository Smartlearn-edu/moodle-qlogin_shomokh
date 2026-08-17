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

/** Tests secure lookup and authentication for linking an existing account. */
final class account_link_test extends \advanced_testcase {
    public function test_unique_email_and_password_authenticate_existing_account(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'username' => 'legacy.student',
            'email' => 'legacy@example.test',
            'password' => 'Correct-password-42',
        ]);

        $authenticated = account_link::authenticate('LEGACY@example.test', 'Correct-password-42');
        $this->assertNotNull($authenticated);
        $this->assertSame((int)$user->id, (int)$authenticated->id);
    }

    public function test_wrong_password_does_not_authenticate(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user([
            'username' => 'legacy.student',
            'email' => 'legacy@example.test',
            'password' => 'Correct-password-42',
        ]);

        $this->assertNull(account_link::authenticate('legacy@example.test', 'wrong-password'));
    }

    public function test_duplicate_email_is_not_resolved_automatically(): void {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user([
            'username' => 'legacy.one',
            'email' => 'shared@example.test',
            'password' => 'Correct-password-42',
        ]);
        $second = $this->getDataGenerator()->create_user([
            'username' => 'legacy.two',
            'email' => 'second@example.test',
            'password' => 'Correct-password-42',
        ]);
        $DB->set_field('user', 'email', 'shared@example.test', ['id' => $second->id]);

        $this->assertNull(account_link::authenticate('shared@example.test', 'Correct-password-42'));
        $this->assertTrue(account_link::email_is_ambiguous('shared@example.test'));
    }

    public function test_phone_email_and_username_resolve_to_the_same_account(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'username' => '14155550123',
            'email' => 'multi-login@example.test',
        ]);

        $this->assertSame($user->username, account_link::resolve_username('+1 415 555 0123'));
        $this->assertSame($user->username, account_link::resolve_username('MULTI-LOGIN@example.test'));
        $this->assertSame($user->username, account_link::resolve_username('14155550123'));
    }

    public function test_pending_verification_does_not_block_email_authentication(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requireemail', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user([
            'username' => '14155550124',
            'email' => 'pending-login@example.test',
            'password' => 'Current-password-42',
        ]);
        verification::track_new_user($user);

        $authenticated = account_link::authenticate('PENDING-LOGIN@example.test', 'Current-password-42');
        $this->assertNotNull($authenticated);
        $this->assertSame((int)$user->id, (int)$authenticated->id);
        $this->assertFalse(verification::channel_complete((int)$user->id, verification::EMAIL));
    }
}
