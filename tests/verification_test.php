<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Unit tests for channel-specific verification helpers. */
final class verification_test extends \advanced_testcase {
    public function test_default_auth_policy_includes_manual_and_email(): void {
        $this->resetAfterTest();
        unset_config('authtypes', 'local_qlogin_shomokh');

        $this->assertSame(['manual', 'email'], auth_policy::allowed_types());
        $this->assertTrue(auth_policy::allows((object)['auth' => 'manual']));
        $this->assertTrue(auth_policy::allows((object)['auth' => 'email']));
    }

    public function test_email_auth_legacy_login_starts_verification_when_allowed(): void {
        $this->resetAfterTest();
        set_config('enabled', 0, 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user([
            'username' => 'legacy.email.auth',
            'auth' => 'email',
            'email' => 'legacy-email-auth@example.test',
        ]);
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requireemail', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual,email', 'local_qlogin_shomokh');

        $records = verification::bootstrap_existing_user($user);

        $this->assertSame(verification::VERIFIED, $records[verification::EMAIL]->state);
        $this->assertSame(verification::PENDING, $records[verification::PHONE]->state);
    }

    public function test_whatsapp_url_rejects_meta_identifiers(): void {
        $this->resetAfterTest();
        set_config('businessnumber', '1234567890123456', 'local_qlogin_shomokh');
        $this->assertNull(verification::whatsapp_url('ABCDEFG234'));

        set_config('businessnumber', '+1 415 555 2671', 'local_qlogin_shomokh');
        $this->assertSame(
            'https://wa.me/14155552671?text=SHOMOKH%20VERIFY%20ABCDEFG234',
            verification::whatsapp_url('ABCDEFG234')
        );
    }

    public function test_cooldown_is_calculated_from_the_supplied_channel_record_only(): void {
        $this->resetAfterTest();
        set_config('resendcooldown', 600, 'local_qlogin_shomokh');
        $emailrecord = (object)['lastsentat' => time() - 100];
        $phonerecord = (object)['lastsentat' => 0];

        $this->assertGreaterThanOrEqual(499, verification::cooldown_remaining($emailrecord));
        $this->assertLessThanOrEqual(500, verification::cooldown_remaining($emailrecord));
        $this->assertSame(0, verification::cooldown_remaining($phonerecord));
    }

    public function test_legacy_first_login_trusts_email_and_starts_phone_grace(): void {
        $this->resetAfterTest();
        set_config('enabled', 0, 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user([
            'username' => 'legacy.before.plugin',
            'email' => 'verified-legacy@example.test',
        ]);
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requireemail', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        set_config('graceperioddays', 30, 'local_qlogin_shomokh');

        $records = verification::bootstrap_existing_user($user);

        $this->assertSame(verification::VERIFIED, $records[verification::EMAIL]->state);
        $this->assertSame(verification::PENDING, $records[verification::PHONE]->state);
        $this->assertSame('', $records[verification::PHONE]->target);
        $this->assertGreaterThan(time() + (29 * DAYSECS), (int)$records[verification::PHONE]->expiresat);
    }

    public function test_unverified_phone_change_does_not_restart_grace_period(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user(['username' => '14155550123']);
        $record = verification::ensure_channel($user, verification::PHONE);
        $originalcreated = (int)$record->timecreated;
        $originalexpiry = (int)$record->expiresat;
        $DB->insert_record('local_qlogin_shomokh_alias', (object)[
            'userid' => $user->id,
            'phone' => '442071838750',
            'source' => 'selfchange',
            'status' => 'active',
            'verified' => 0,
            'createdby' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $changed = verification::ensure_channel($user, verification::PHONE);
        $this->assertSame($originalcreated, (int)$changed->timecreated);
        $this->assertSame($originalexpiry, (int)$changed->expiresat);
        $this->assertSame('442071838750', $changed->target);
    }

    public function test_new_account_is_not_mistaken_for_trusted_legacy_account(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requireemail', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user([
            'username' => '14155550125',
            'email' => 'new-pending@example.test',
        ]);

        verification::track_new_user($user);
        $records = verification::bootstrap_existing_user($user);

        $this->assertSame(verification::PENDING, $records[verification::EMAIL]->state);
        $this->assertSame(verification::PENDING, $records[verification::PHONE]->state);
    }

    public function test_whatsapp_code_selects_pending_record_before_old_verified_duplicate(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 0, 'local_qlogin_shomokh');
        $olduser = $this->getDataGenerator()->create_user(['username' => 'legacy.old.verified']);
        $currentuser = $this->getDataGenerator()->create_user(['username' => 'legacy.current.pending']);
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        $now = time();
        $phone = '966534390821';
        $code = 'PQUQ6HYW6Z';
        $base = [
            'channel' => verification::PHONE,
            'target' => $phone,
            'expiresat' => $now + DAYSECS,
            'lastsentat' => $now,
            'remindercount' => 0,
            'lastremindedat' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('local_qlogin_shomokh_verify', (object)([
            'userid' => $olduser->id,
            'tokenhash' => '',
            'state' => verification::VERIFIED,
            'verifiedat' => $now,
        ] + $base));
        $pendingid = $DB->insert_record('local_qlogin_shomokh_verify', (object)([
            'userid' => $currentuser->id,
            'tokenhash' => manager::hash_token($code),
            'state' => verification::PENDING,
            'verifiedat' => null,
        ] + $base));

        $result = verification::verify_from_whatsapp($phone, 'SHOMOKH VERIFY ' . $code);

        $this->assertSame('verified', $result['status']);
        $this->assertSame((int)$currentuser->id, (int)$result['userid']);
        $this->assertSame(verification::VERIFIED,
            $DB->get_field('local_qlogin_shomokh_verify', 'state', ['id' => $pendingid]));
    }
}
