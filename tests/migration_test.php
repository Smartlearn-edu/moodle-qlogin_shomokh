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
 * Migration test functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Tests non-destructive legacy phone migration and alias resolution.
 */
final class migration_test extends \advanced_testcase {
    public function test_email_auth_account_can_self_claim_only_when_configured(): void {
        $this->resetAfterTest();
        set_config('selfclaimenabled', 1, 'local_qlogin_shomokh');
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user([
            'username' => 'legacy.email.auth',
            'auth' => 'email',
            'phone1' => '',
        ]);

        set_config('authtypes', 'manual,email', 'local_qlogin_shomokh');
        $this->assertTrue(migration::can_self_claim($user));

        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        $this->assertFalse(migration::can_self_claim($user));
    }

    public function test_legacy_local_phone_requires_explicit_default_dialling_code(): void {
        $this->resetAfterTest();
        set_config('legacydefaultcountrycode', '999', 'local_qlogin_shomokh');
        $this->assertSame('999123456789', migration::normalise_legacy_phone('012 345 6789'));
        $this->assertSame('12025550123', migration::normalise_legacy_phone('+1 202 555 0123'));
        set_config('legacydefaultcountrycode', '', 'local_qlogin_shomokh');
        $this->assertSame('', migration::normalise_legacy_phone('012 345 6789'));
    }

    public function test_active_alias_resolves_to_original_username(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['username' => 'legacy.student']);
        $now = time();
        $DB->insert_record('local_qlogin_shomokh_alias', (object)[
            'userid' => $user->id,
            'phone' => '999123456789',
            'source' => 'phone1',
            'status' => 'active',
            'verified' => 0,
            'createdby' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $this->assertSame('legacy.student', migration::resolve_username('999123456789'));
        $this->assertTrue(migration::alias_exists('999123456789'));
    }

    public function test_existing_account_claim_preserves_identity_and_adds_phone_alias(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        set_config('selfclaimenabled', 1, 'local_qlogin_shomokh');
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user([
            'username' => 'legacy.student',
            'phone1' => '',
        ]);

        $this->assertSame('created', migration::claim_phone($user, '+999 123 456 789'));
        $freshuser = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $this->assertSame($user->id, $freshuser->id);
        $this->assertSame('legacy.student', $freshuser->username);
        $this->assertSame('999123456789', $freshuser->phone1);
        $this->assertSame('999123456789', migration::phone_for_user($freshuser));
        $this->assertSame('legacy.student', migration::resolve_username('999123456789'));
    }

    public function test_existing_account_claim_rejects_phone_owned_by_another_user(): void {
        $this->resetAfterTest();
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        set_config('selfclaimenabled', 1, 'local_qlogin_shomokh');
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        $this->getDataGenerator()->create_user(['username' => '999123456789']);
        $legacy = $this->getDataGenerator()->create_user(['username' => 'legacy.student']);

        $this->expectException(\moodle_exception::class);
        migration::claim_phone($legacy, '999123456789');
    }

    public function test_whatsapp_completion_marks_matching_alias_verified(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['username' => 'legacy.student']);
        $now = time();
        $aliasid = $DB->insert_record('local_qlogin_shomokh_alias', (object)[
            'userid' => $user->id,
            'phone' => '999123456789',
            'source' => 'selfclaim',
            'status' => 'active',
            'verified' => 0,
            'createdby' => $user->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        migration::mark_alias_verified((int)$user->id, '999123456789');
        $this->assertEquals(1, $DB->get_field('local_qlogin_shomokh_alias', 'verified', ['id' => $aliasid]));
    }

    public function test_phone_change_keeps_username_but_replaces_phone_login(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        set_config('selfclaimenabled', 1, 'local_qlogin_shomokh');
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user(['username' => '14155550123']);

        $this->assertSame('created', migration::set_phone($user, '442071838750', true));
        $freshuser = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $this->assertSame('14155550123', $freshuser->username);
        $this->assertSame('442071838750', migration::phone_for_user($freshuser));
        $this->assertNull(migration::resolve_phone_login('14155550123'));
        $this->assertSame('14155550123', migration::resolve_phone_login('442071838750'));
    }

    public function test_registration_conflict_detects_formatted_legacy_phone_field(): void {
        $this->resetAfterTest();
        set_config('enabled', 0, 'local_qlogin_shomokh');
        set_config('legacydefaultcountrycode', '966', 'local_qlogin_shomokh');
        $legacy = $this->getDataGenerator()->create_user([
            'username' => 'legacy.with.phone.field',
            'phone1' => '+966 53 439 0821',
        ]);

        $this->assertTrue(migration::phone_in_use('966534390821'));
        $this->assertFalse(migration::phone_in_use('966534390821', (int)$legacy->id));
    }

    public function test_registration_conflict_detects_another_users_verification_target(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 0, 'local_qlogin_shomokh');
        $legacy = $this->getDataGenerator()->create_user(['username' => 'legacy.verified.phone']);
        $now = time();
        $DB->insert_record('local_qlogin_shomokh_verify', (object)[
            'userid' => $legacy->id,
            'channel' => verification::PHONE,
            'target' => '966534390821',
            'tokenhash' => '',
            'state' => verification::VERIFIED,
            'expiresat' => $now,
            'verifiedat' => $now,
            'lastsentat' => 0,
            'remindercount' => 0,
            'lastremindedat' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $this->assertTrue(migration::phone_in_use('966534390821'));
        $this->assertFalse(migration::phone_in_use('966534390821', (int)$legacy->id));
    }

    public function test_admin_repair_corrects_duplicate_country_code_but_keeps_stale_history(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 0, 'local_qlogin_shomokh');
        $staleuser = $this->getDataGenerator()->create_user(['username' => 'stale.phone.history']);
        $current = $this->getDataGenerator()->create_user([
            'username' => 'legacy.duplicated.country',
            'phone1' => '966966534390821',
        ]);
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        set_config('legacydefaultcountrycode', '966', 'local_qlogin_shomokh');
        $now = time();
        $DB->insert_record('local_qlogin_shomokh_alias', (object)[
            'userid' => $current->id,
            'phone' => '966966534390821',
            'source' => 'selfclaim',
            'status' => 'active',
            'verified' => 0,
            'createdby' => $current->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        foreach (
            [
            [$staleuser->id, '966534390821', verification::VERIFIED, ''],
            [$current->id, '966966534390821', verification::PENDING, manager::hash_token('WD44FUATVY')],
            ] as [$userid, $target, $state, $tokenhash]
        ) {
            $DB->insert_record('local_qlogin_shomokh_verify', (object)[
                'userid' => $userid,
                'channel' => verification::PHONE,
                'target' => $target,
                'tokenhash' => $tokenhash,
                'state' => $state,
                'expiresat' => $now + DAYSECS,
                'verifiedat' => $state === verification::VERIFIED ? $now : null,
                'lastsentat' => $now,
                'remindercount' => 0,
                'lastremindedat' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        $corrected = migration::repair_repeated_country_code(
            (int)$current->id,
            (int)get_admin()->id
        );

        $this->assertSame('966534390821', $corrected);
        $this->assertSame(
            '966534390821',
            $DB->get_field('local_qlogin_shomokh_alias', 'phone', ['userid' => $current->id])
        );
        $this->assertSame(
            '966534390821',
            $DB->get_field('user', 'phone1', ['id' => $current->id])
        );
        $this->assertSame('966534390821', $DB->get_field(
            'local_qlogin_shomokh_verify',
            'target',
            ['userid' => $current->id, 'channel' => verification::PHONE]
        ));
        $this->assertSame(verification::PENDING, $DB->get_field(
            'local_qlogin_shomokh_verify',
            'state',
            ['userid' => $current->id, 'channel' => verification::PHONE]
        ));
    }
}
