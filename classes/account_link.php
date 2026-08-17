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

/** Existing-account authentication for self-service phone linking. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Resolves an unambiguous legacy account and verifies its current password. */
final class account_link {
    /** Resolves a phone, unique email or exact username to Moodle's username. */
    public static function resolve_username(string $identifier): ?string {
        global $DB;

        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $email = manager::normalise_email($identifier);
        if ($email !== '' && strpos($identifier, '@') !== false) {
            $emailsql = $DB->sql_equal('email', ':email', false);
            $users = $DB->get_records_select('user', "$emailsql AND deleted = :deleted", [
                'email' => $email,
                'deleted' => 0,
            ], 'id ASC', 'id, username', 0, 2);
            if (count($users) === 1) {
                return (string)reset($users)->username;
            }
            // An email-shaped exact username is still unambiguous.
            $candidate = \core_text::strtolower(clean_param($identifier, PARAM_USERNAME));
            $exactuser = $candidate === '' ? false : $DB->get_record('user', [
                'username' => $candidate,
                'deleted' => 0,
            ], 'id, username');
            return $exactuser ? (string)$exactuser->username : null;
        }

        if (preg_match('/^[\d\s+().\-٠-٩۰-۹]+$/u', $identifier)) {
            $phone = manager::normalise_phone($identifier);
            if ($phone !== '') {
                return migration::resolve_phone_login($phone);
            }
        }

        $username = \core_text::strtolower(clean_param($identifier, PARAM_USERNAME));
        if ($username === '') {
            return null;
        }
        $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], 'id, username');
        return $user ? (string)$user->username : null;
    }

    /** Whether an email cannot be used for login because it belongs to multiple accounts. */
    public static function email_is_ambiguous(string $identifier): bool {
        global $DB;
        $email = manager::normalise_email($identifier);
        if ($email === '' || strpos($identifier, '@') === false) {
            return false;
        }
        $candidate = \core_text::strtolower(clean_param(trim($identifier), PARAM_USERNAME));
        if ($candidate !== '' && $DB->record_exists('user', ['username' => $candidate, 'deleted' => 0])) {
            return false;
        }
        $emailsql = $DB->sql_equal('email', ':email', false);
        return $DB->count_records_select('user', "$emailsql AND deleted = :deleted", [
            'email' => $email,
            'deleted' => 0,
        ]) > 1;
    }

    /** Returns the authenticated account, or null without exposing which check failed. */
    public static function authenticate(string $identifier, string $password): ?\stdClass {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return null;
        }
        $username = self::resolve_username($identifier);
        if ($username === null) {
            return null;
        }

        try {
            $user = authenticate_user_login($username, $password);
        } catch (\Throwable $exception) {
            return null;
        }
        if (!$user || !empty($user->deleted) || !empty($user->suspended) || is_siteadmin($user->id)) {
            return null;
        }
        return $user;
    }

    /** Rechecks the current password before changing a sign-in phone. */
    public static function reauthenticate(\stdClass $user, string $password): bool {
        if ($password === '' || empty($user->username)) {
            return false;
        }
        try {
            $authenticated = authenticate_user_login((string)$user->username, $password);
        } catch (\Throwable $exception) {
            return false;
        }
        return $authenticated && (int)$authenticated->id === (int)$user->id;
    }
}
