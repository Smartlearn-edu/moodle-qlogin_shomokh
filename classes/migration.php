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
 * Migration functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Produces a dry-run and links only unique, unambiguous phone aliases.
 */
final class migration {
    /**
     * Whether self-service linking is enabled with phone verification ready.
     */
    public static function self_claim_available(): bool {
        global $DB;
        return (bool)get_config('local_qlogin_shomokh', 'selfclaimenabled')
            && (bool)get_config('local_qlogin_shomokh', 'enabled')
            && (bool)get_config('local_qlogin_shomokh', 'requirephone')
            && $DB->get_manager()->table_exists('local_qlogin_shomokh_alias')
            && $DB->get_manager()->table_exists('local_qlogin_shomokh_verify');
    }

    /**
     * Whether an account may attach its first phone alias through self-service.
     */
    public static function can_self_claim(\stdClass $user): bool {
        if (
            empty($user->id) || !empty($user->deleted) || !empty($user->suspended)
                || is_siteadmin($user->id)
                || !self::self_claim_available()
        ) {
            return false;
        }
        return auth_policy::allows($user)
            && self::phone_for_user($user) === '';
    }

    /**
     * Attaches a phone to the authenticated account without changing its userid or username.
     *
     * @return string created or existing.
     */
    public static function claim_phone(\stdClass $user, string $phone): string {
        return self::set_phone($user, $phone, false);
    }

    /**
     * Changes or adds a sign-in phone after account authentication.
     */
    public static function set_phone(\stdClass $user, string $phone, bool $allowchange): string {
        global $CFG, $DB;

        $phone = manager::normalise_phone($phone);
        if ($phone === '') {
            throw new \moodle_exception('error:phone', 'local_qlogin_shomokh');
        }
        $current = empty($user->id) ? '' : self::phone_for_user($user);
        if ($current !== '') {
            if ($current === $phone) {
                return 'existing';
            }
            if (!$allowchange) {
                throw new \moodle_exception('claim:accountalreadylinked', 'local_qlogin_shomokh');
            }
        }
        if (
            empty($user->id) || (!$allowchange && !self::can_self_claim($user))
                || ($allowchange && !self::user_can_manage_phone($user))
        ) {
            throw new \moodle_exception('claim:noteligible', 'local_qlogin_shomokh');
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_qlogin_shomokh');
        $userlock = $lockfactory->get_lock('selfclaim_user_' . (int)$user->id, 5);
        if (!$userlock) {
            throw new \moodle_exception('claim:busy', 'local_qlogin_shomokh');
        }
        $phonelock = $lockfactory->get_lock('phone_' . hash('sha256', $phone), 5);
        if (!$phonelock) {
            $userlock->release();
            throw new \moodle_exception('claim:busy', 'local_qlogin_shomokh');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $freshuser = $DB->get_record('user', [
                'id' => $user->id,
                'deleted' => 0,
                'suspended' => 0,
            ], '*', MUST_EXIST);
            $existing = $DB->get_record('local_qlogin_shomokh_alias', ['userid' => $freshuser->id]);
            if ($existing) {
                if ((string)$existing->phone === $phone && (string)$existing->status === 'active') {
                    $transaction->allow_commit();
                    return 'existing';
                }
                if (!$allowchange && (string)$existing->phone !== $phone) {
                    throw new \moodle_exception('claim:accountalreadylinked', 'local_qlogin_shomokh');
                }
            }

            if (self::phone_in_use($phone, (int)$freshuser->id)) {
                throw new \moodle_exception('claim:phoneused', 'local_qlogin_shomokh');
            }

            $now = time();
            if ($existing) {
                $existing->phone = $phone;
                $existing->status = 'active';
                $existing->verified = 0;
                $existing->source = $allowchange ? 'selfchange' : 'selfclaim';
                $existing->createdby = $freshuser->id;
                $existing->timemodified = $now;
                $DB->update_record('local_qlogin_shomokh_alias', $existing);
            } else {
                try {
                    $DB->insert_record('local_qlogin_shomokh_alias', (object)[
                        'userid' => $freshuser->id,
                        'phone' => $phone,
                        'source' => $allowchange ? 'selfchange' : 'selfclaim',
                        'status' => 'active',
                        'verified' => 0,
                        'createdby' => $freshuser->id,
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ]);
                } catch (\dml_write_exception $exception) {
                    throw new \moodle_exception('claim:phoneused', 'local_qlogin_shomokh');
                }
            }

            if ($allowchange || trim((string)$freshuser->phone1) === '') {
                require_once($CFG->dirroot . '/user/lib.php');
                $freshuser->phone1 = $phone;
                user_update_user($freshuser, false, false);
            }
            $transaction->allow_commit();
            return 'created';
        } finally {
            $phonelock->release();
            $userlock->release();
        }
    }

    /**
     * Whether a signed-in account is allowed to add or replace its phone.
     */
    public static function user_can_manage_phone(\stdClass $user): bool {
        if (
            empty($user->id) || !empty($user->deleted) || !empty($user->suspended)
                || is_siteadmin($user->id) || !self::self_claim_available()
        ) {
            return false;
        }
        return auth_policy::allows($user);
    }

    /**
     * Marks an active alias verified after the matching WhatsApp message succeeds.
     */
    public static function mark_alias_verified(int $userid, string $phone): void {
        global $DB;
        $phone = manager::normalise_phone($phone);
        if ($phone === '') {
            return;
        }
        $alias = $DB->get_record('local_qlogin_shomokh_alias', [
            'userid' => $userid,
            'phone' => $phone,
            'status' => 'active',
        ]);
        if ($alias && empty($alias->verified)) {
            $alias->verified = 1;
            $alias->timemodified = time();
            $DB->update_record('local_qlogin_shomokh_alias', $alias);
        }
    }

    /**
     * Trusts one administrator-reviewed batch of pre-phone account emails.
     */
    public static function trust_legacy_emails(int $batchsize = 500): int {
        global $DB;
        if (!verification::available()) {
            return 0;
        }
        [$authsql, $params] = $DB->get_in_or_equal(auth_policy::allowed_types(), SQL_PARAMS_NAMED, 'trustauth');
        $users = $DB->get_records_select(
            'user',
            "deleted = :deleted AND suspended = :suspended AND auth $authsql",
            [
                'deleted' => 0,
                'suspended' => 0,
            ] + $params,
            'id ASC',
            'id, username, email, auth, deleted, suspended'
        );
        $admins = [];
        foreach (get_admins() as $admin) {
            $admins[(int)$admin->id] = true;
        }
        $trusted = 0;
        $limit = max(1, min(2000, $batchsize));
        foreach ($users as $user) {
            $usernameisphone = preg_match('/^[\d\s+().\-٠-٩۰-۹]+$/u', (string)$user->username)
                && manager::normalise_phone((string)$user->username) !== '';
            if (
                isset($admins[(int)$user->id]) || $usernameisphone
                    || manager::normalise_email((string)$user->email) === ''
            ) {
                continue;
            }
            if (verification::trust_legacy_email($user)) {
                $trusted++;
                if ($trusted >= $limit) {
                    break;
                }
            }
        }
        return $trusted;
    }

    /**
     * Returns summary, limited details and every safe candidate.
     */
    public static function scan(int $detaillimit = 200): array {
        global $DB;
        $admins = [];
        foreach (get_admins() as $admin) {
            $admins[(int)$admin->id] = true;
        }
        [$authsql, $authparams] = $DB->get_in_or_equal(
            auth_policy::allowed_types(),
            SQL_PARAMS_NAMED,
            'migauth'
        );
        $guestid = (int)guest_user()->id;
        $fields = array_merge(
            ['id', 'username', 'email', 'phone1', 'phone2', 'auth', 'suspended'],
            \core_user\fields::for_name()->get_required_fields()
        );
        $fields = implode(', ', array_unique($fields));
        $users = $DB->get_records_select(
            'user',
            "deleted = :deleted AND id <> :guestid AND auth $authsql",
            ['deleted' => 0, 'guestid' => $guestid] + $authparams,
            'id ASC',
            $fields
        );
        foreach (array_keys($admins) as $adminid) {
            unset($users[$adminid]);
        }

        $aliases = $DB->get_records('local_qlogin_shomokh_alias');
        $aliasesbyuser = [];
        $aliasesbyphone = [];
        foreach ($aliases as $alias) {
            $aliasesbyuser[(int)$alias->userid] = $alias;
            $aliasesbyphone[(string)$alias->phone] = $alias;
        }

        $prepared = [];
        $owners = [];
        $emails = [];
        foreach ($users as $user) {
            $phoneusername = manager::normalise_phone((string)$user->username);
            if ($phoneusername !== '') {
                $owners[$phoneusername][(int)$user->id] = true;
            }
            $candidatevalues = [];
            $hadphonevalue = false;
            foreach (['phone1', 'phone2'] as $source) {
                $raw = trim((string)$user->{$source});
                if ($raw === '') {
                    continue;
                }
                $hadphonevalue = true;
                $candidate = self::normalise_legacy_phone($raw);
                if ($candidate !== '') {
                    $candidatevalues[$candidate] = $source;
                    $owners[$candidate][(int)$user->id] = true;
                }
            }
            $email = manager::normalise_email((string)$user->email);
            if ($email !== '') {
                $emails[$email][(int)$user->id] = true;
            }
            $prepared[(int)$user->id] = [
                'user' => $user,
                'phoneusername' => $phoneusername,
                'candidates' => $candidatevalues,
                'hadphonevalue' => $hadphonevalue,
            ];
        }

        $summary = array_fill_keys(['total', 'phoneusername', 'mapped', 'safe', 'duplicate', 'missing',
            'invalid', 'duplicateemail'], 0);
        $summary['total'] = count($prepared);
        $details = [];
        $safe = [];
        foreach ($prepared as $userid => $item) {
            $category = 'invalid';
            $phone = '';
            $source = '';
            if ($item['phoneusername'] !== '') {
                $category = 'phoneusername';
                $phone = $item['phoneusername'];
                $source = 'username';
            } else if (isset($aliasesbyuser[$userid]) && $aliasesbyuser[$userid]->status === 'active') {
                $category = 'mapped';
                $phone = (string)$aliasesbyuser[$userid]->phone;
                $source = (string)$aliasesbyuser[$userid]->source;
            } else if (count($item['candidates']) === 0) {
                $category = $item['hadphonevalue'] ? 'invalid' : 'missing';
            } else if (count($item['candidates']) > 1) {
                $category = 'invalid';
            } else {
                $phone = (string)array_key_first($item['candidates']);
                $source = (string)$item['candidates'][$phone];
                $ownerids = array_keys($owners[$phone] ?? []);
                $existingalias = $aliasesbyphone[$phone] ?? null;
                if (
                    count($ownerids) === 1 && (int)$ownerids[0] === $userid
                        && (!$existingalias || (int)$existingalias->userid === $userid)
                ) {
                    $category = 'safe';
                    $safe[] = ['userid' => $userid, 'phone' => $phone, 'source' => $source];
                } else {
                    $category = 'duplicate';
                }
            }
            $summary[$category]++;
            $email = manager::normalise_email((string)$item['user']->email);
            $duplicateemail = $email !== '' && count($emails[$email] ?? []) > 1;
            if ($duplicateemail) {
                $summary['duplicateemail']++;
            }
            if (count($details) < $detaillimit) {
                $details[] = (object)[
                    'userid' => $userid,
                    'fullname' => fullname($item['user']),
                    'category' => $category,
                    'phone' => $phone,
                    'source' => $source,
                    'duplicateemail' => $duplicateemail,
                ];
            }
        }
        return ['summary' => $summary, 'details' => $details, 'safe' => $safe];
    }

    /**
     * Inserts up to one batch of still-safe aliases and returns the count.
     */
    public static function link_safe(int $createdby, int $batchsize = 500): int {
        global $DB;
        $scan = self::scan(0);
        $count = 0;
        foreach (array_slice($scan['safe'], 0, max(1, min(2000, $batchsize))) as $candidate) {
            if (
                $DB->record_exists('local_qlogin_shomokh_alias', ['userid' => $candidate['userid']])
                    || $DB->record_exists('local_qlogin_shomokh_alias', ['phone' => $candidate['phone']])
            ) {
                continue;
            }
            $user = $DB->get_record('user', ['id' => $candidate['userid'], 'deleted' => 0]);
            if (!$user || manager::normalise_phone((string)$user->username) !== '') {
                continue;
            }
            $now = time();
            try {
                $DB->insert_record('local_qlogin_shomokh_alias', (object)[
                    'userid' => $candidate['userid'],
                    'phone' => $candidate['phone'],
                    'source' => $candidate['source'],
                    'status' => 'active',
                    'verified' => 0,
                    'createdby' => $createdby,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $count++;
            } catch (\dml_write_exception $exception) {
                // A concurrent administrator or batch may have linked it first.
                unset($exception);
            }
        }
        return $count;
    }

    /**
     * Resolves an active phone alias to the original Moodle username.
     */
    public static function resolve_username(string $phone): ?string {
        global $DB;
        $phone = manager::normalise_phone($phone);
        if ($phone === '' || !$DB->get_manager()->table_exists('local_qlogin_shomokh_alias')) {
            return null;
        }
        $sql = "SELECT u.username
                  FROM {local_qlogin_shomokh_alias} a
                  JOIN {user} u ON u.id = a.userid
                 WHERE a.phone = :phone AND a.status = :status
                       AND u.deleted = :deleted AND u.suspended = :suspended";
        $username = $DB->get_field_sql($sql, [
            'phone' => $phone,
            'status' => 'active',
            'deleted' => 0,
            'suspended' => 0,
        ]);
        return $username === false ? null : (string)$username;
    }

    /**
     * Resolves a current phone while rejecting an original phone replaced by an alias.
     */
    public static function resolve_phone_login(string $phone): ?string {
        global $DB;
        $phone = manager::normalise_phone($phone);
        if ($phone === '') {
            return null;
        }
        $aliasusername = self::resolve_username($phone);
        if ($aliasusername !== null) {
            return $aliasusername;
        }
        $user = $DB->get_record('user', [
            'username' => $phone,
            'deleted' => 0,
        ], 'id, username, suspended');
        if (!$user) {
            return null;
        }
        if (!$DB->get_manager()->table_exists('local_qlogin_shomokh_alias')) {
            return (string)$user->username;
        }
        $alias = $DB->get_record('local_qlogin_shomokh_alias', [
            'userid' => $user->id,
            'status' => 'active',
        ], 'id, phone');
        if ($alias && manager::normalise_phone((string)$alias->phone) !== $phone) {
            return null;
        }
        return (string)$user->username;
    }

    /**
     * Whether a phone is already claimed by an alias.
     */
    public static function alias_exists(string $phone): bool {
        global $DB;
        $phone = manager::normalise_phone($phone);
        return $phone !== '' && $DB->get_manager()->table_exists('local_qlogin_shomokh_alias')
            && $DB->record_exists('local_qlogin_shomokh_alias', [
            'phone' => $phone,
            'status' => 'active',
        ]);
    }

    /**
     * Checks every supported identity source before a phone is assigned.
     *
     * Legacy accounts may keep their number only in phone1/phone2 or in a
     * verification row, so checking usernames and aliases alone is unsafe.
     */
    public static function phone_in_use(
        string $phone,
        int $excludeuserid = 0,
        bool $includeverification = true
    ): bool {
        global $DB;

        $phone = manager::normalise_phone($phone);
        if ($phone === '') {
            return false;
        }

        $params = ['phone' => $phone, 'deleted' => 0];
        $exclude = '';
        if ($excludeuserid > 0) {
            $exclude = ' AND id <> :excludeuserid';
            $params['excludeuserid'] = $excludeuserid;
        }
        if ($DB->record_exists_select('user', 'username = :phone AND deleted = :deleted' . $exclude, $params)) {
            return true;
        }

        if ($DB->get_manager()->table_exists('local_qlogin_shomokh_alias')) {
            $aliasparams = ['phone' => $phone, 'status' => 'active'];
            $aliasexclude = '';
            if ($excludeuserid > 0) {
                $aliasexclude = ' AND userid <> :aliasexcludeuserid';
                $aliasparams['aliasexcludeuserid'] = $excludeuserid;
            }
            if (
                $DB->record_exists_select(
                    'local_qlogin_shomokh_alias',
                    'phone = :phone AND status = :status' . $aliasexclude,
                    $aliasparams
                )
            ) {
                return true;
            }
        }

        if ($includeverification && $DB->get_manager()->table_exists('local_qlogin_shomokh_verify')) {
            $verifyparams = [
                'channel' => verification::PHONE,
                'target' => $phone,
                'deleted' => 0,
            ];
            $verifyexclude = '';
            if ($excludeuserid > 0) {
                $verifyexclude = ' AND v.userid <> :verifyexcludeuserid';
                $verifyparams['verifyexcludeuserid'] = $excludeuserid;
            }
            $sql = "SELECT 1
                      FROM {local_qlogin_shomokh_verify} v
                      JOIN {user} u ON u.id = v.userid
                     WHERE v.channel = :channel AND v.target = :target
                           AND u.deleted = :deleted$verifyexclude";
            if ($DB->record_exists_sql($sql, $verifyparams)) {
                return true;
            }
        }

        $legacyparams = ['deleted' => 0, 'emptyphone1' => '', 'emptyphone2' => ''];
        $legacyexclude = '';
        if ($excludeuserid > 0) {
            $legacyexclude = ' AND id <> :legacyexcludeuserid';
            $legacyparams['legacyexcludeuserid'] = $excludeuserid;
        }
        $legacyusers = $DB->get_records_select(
            'user',
            "deleted = :deleted AND (phone1 <> :emptyphone1 OR phone2 <> :emptyphone2)$legacyexclude",
            $legacyparams,
            '',
            'id, phone1, phone2'
        );
        foreach ($legacyusers as $legacyuser) {
            foreach (['phone1', 'phone2'] as $field) {
                if (self::normalise_legacy_phone((string)$legacyuser->{$field}) === $phone) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Repairs one account whose selected dialling code was accidentally saved twice.
     *
     * The correction is deterministic: it removes exactly one configured
     * legacy country-code prefix. Existing live usernames, aliases and core
     * phone fields remain protected. A stale verification row alone does not
     * block the repair because the WhatsApp code selects the correct pending
     * record after 0.6.1.
     */
    public static function repair_repeated_country_code(int $userid, int $adminid): string {
        global $CFG, $DB;

        $user = $DB->get_record('user', [
            'id' => $userid,
            'deleted' => 0,
            'suspended' => 0,
        ], '*', MUST_EXIST);
        $countrycode = preg_replace('/\D/', '', (string)get_config(
            'local_qlogin_shomokh',
            'legacydefaultcountrycode'
        ));
        $current = self::phone_for_user($user);
        $corrected = manager::remove_repeated_country_code($current, $countrycode);
        if ($countrycode === '' || $corrected === '' || $corrected === $current) {
            throw new \moodle_exception('migration:repairnotneeded', 'local_qlogin_shomokh');
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_qlogin_shomokh');
        $userlock = $lockfactory->get_lock('selfclaim_user_' . $userid, 5);
        if (!$userlock) {
            throw new \moodle_exception('claim:busy', 'local_qlogin_shomokh');
        }
        $phonelock = $lockfactory->get_lock('phone_' . hash('sha256', $corrected), 5);
        if (!$phonelock) {
            $userlock->release();
            throw new \moodle_exception('claim:busy', 'local_qlogin_shomokh');
        }
        try {
            if (self::phone_in_use($corrected, $userid, false)) {
                throw new \moodle_exception('claim:phoneused', 'local_qlogin_shomokh');
            }
            $transaction = $DB->start_delegated_transaction();
            $now = time();
            $alias = $DB->get_record('local_qlogin_shomokh_alias', ['userid' => $userid]);
            if ($alias) {
                $alias->phone = $corrected;
                $alias->status = 'active';
                $alias->verified = 0;
                $alias->source = 'adminrepair';
                $alias->createdby = $adminid;
                $alias->timemodified = $now;
                $DB->update_record('local_qlogin_shomokh_alias', $alias);
            } else if (manager::normalise_phone((string)$user->username) !== $corrected) {
                $DB->insert_record('local_qlogin_shomokh_alias', (object)[
                    'userid' => $userid,
                    'phone' => $corrected,
                    'source' => 'adminrepair',
                    'status' => 'active',
                    'verified' => 0,
                    'createdby' => $adminid,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }

            require_once($CFG->dirroot . '/user/lib.php');
            $user->phone1 = $corrected;
            user_update_user($user, false, false);
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            verification::ensure_channel($user, verification::PHONE);
            $transaction->allow_commit();
            return $corrected;
        } finally {
            $phonelock->release();
            $userlock->release();
        }
    }

    /**
     * Returns the canonical sign-in phone for a phone-first or aliased user.
     */
    public static function phone_for_user(\stdClass $user): string {
        global $DB;
        if (empty($user->id)) {
            return manager::normalise_phone((string)$user->username);
        }
        if (!$DB->get_manager()->table_exists('local_qlogin_shomokh_alias')) {
            return manager::normalise_phone((string)$user->username);
        }
        $alias = $DB->get_record('local_qlogin_shomokh_alias', [
            'userid' => $user->id,
            'status' => 'active',
        ]);
        if ($alias) {
            return manager::normalise_phone((string)$alias->phone);
        }
        return manager::normalise_phone((string)$user->username);
    }

    /**
     * Safely normalises international values and legacy local values beginning with zero.
     */
    public static function normalise_legacy_phone(string $phone): string {
        $normalised = manager::normalise_phone($phone);
        if ($normalised !== '') {
            return $normalised;
        }
        $arabicdigits = [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];
        $digits = preg_replace('/\D/u', '', strtr($phone, $arabicdigits));
        $countrycode = preg_replace('/\D/', '', (string)get_config(
            'local_qlogin_shomokh',
            'legacydefaultcountrycode'
        ));
        if ($countrycode === '' || strlen($countrycode) > 3 || substr($digits, 0, 1) !== '0') {
            return '';
        }
        return manager::normalise_phone($countrycode . ltrim($digits, '0'));
    }
}
