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

/** Administrator-initiated end-to-end WhatsApp integration test. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Keeps a short-lived test challenge separate from student verification records. */
final class whatsapp_test {
    /** Ten minutes is enough to move between Moodle and WhatsApp. */
    private const LIFETIME = 600;

    /** Creates or returns the current administrator's short-lived test code. */
    public static function issue(int $userid): string {
        global $SESSION;

        $current = self::active_code($userid);
        if ($current !== '') {
            return $current;
        }

        $code = manager::generate_code();
        set_config('whatsapptesthash', manager::hash_token($code), 'local_qlogin_shomokh');
        set_config('whatsapptestexpires', time() + self::LIFETIME, 'local_qlogin_shomokh');
        set_config('whatsapptestuserid', $userid, 'local_qlogin_shomokh');
        $SESSION->local_qlogin_shomokh_whatsapptest = [
            'userid' => $userid,
            'code' => $code,
        ];
        return $code;
    }

    /** Returns the raw code only to the same authenticated session that issued it. */
    public static function active_code(int $userid): string {
        global $SESSION;

        $expires = (int)get_config('local_qlogin_shomokh', 'whatsapptestexpires');
        $owner = (int)get_config('local_qlogin_shomokh', 'whatsapptestuserid');
        $hash = (string)get_config('local_qlogin_shomokh', 'whatsapptesthash');
        $session = $SESSION->local_qlogin_shomokh_whatsapptest ?? [];
        $code = is_array($session) ? (string)($session['code'] ?? '') : '';
        $sessionuserid = is_array($session) ? (int)($session['userid'] ?? 0) : 0;

        if (
            $hash === '' || $expires < time() || $owner !== $userid || $sessionuserid !== $userid
                || $code === '' || !hash_equals($hash, manager::hash_token($code))
        ) {
            return '';
        }
        return $code;
    }

    /** Builds the current site's WhatsApp deep-link without exposing any Meta secret. */
    public static function url(string $code): ?string {
        return verification::whatsapp_url($code, 'TEST');
    }

    /** Validates a signed inbound test message after webhook-level checks pass. */
    public static function verify(string $fromphone, string $message): array {
        $phone = manager::normalise_phone($fromphone);
        if ($phone === '') {
            return ['status' => 'unmatchedphone', 'userid' => null];
        }
        if (!preg_match('/\bSHOMOKH\s+TEST\s+([A-HJ-NP-Z2-9]{10})\b/i', trim($message), $matches)) {
            return ['status' => 'invalidcode', 'userid' => null];
        }

        $owner = (int)get_config('local_qlogin_shomokh', 'whatsapptestuserid');
        $expires = (int)get_config('local_qlogin_shomokh', 'whatsapptestexpires');
        $hash = (string)get_config('local_qlogin_shomokh', 'whatsapptesthash');
        if ($hash === '' || $owner <= 0 || $expires < time()) {
            return ['status' => 'expired', 'userid' => $owner ?: null];
        }
        if (!hash_equals($hash, manager::hash_token($matches[1]))) {
            return ['status' => 'invalidcode', 'userid' => $owner];
        }

        unset_config('whatsapptesthash', 'local_qlogin_shomokh');
        unset_config('whatsapptestexpires', 'local_qlogin_shomokh');
        unset_config('whatsapptestuserid', 'local_qlogin_shomokh');
        return ['status' => 'passed', 'userid' => $owner];
    }
}
