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
 * Manager functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Converts accepted phone input to one digits-only username representation.
 */
final class manager {
    /**
     * Normalises and validates an email address.
     *
     * Moodle's PARAM_EMAIL cleaning alone is not a validity check. Keeping the
     * canonical form here also makes duplicate detection consistent.
     *
     * @param string $email User-supplied email address.
     * @return string Lowercase valid email address, or an empty string.
     */
    public static function normalise_email(string $email): string {
        $email = \core_text::strtolower(trim(clean_param($email, PARAM_EMAIL)));
        return $email !== '' && validate_email($email) ? $email : '';
    }

    /**
     * Normalises an international mobile phone number.
     *
     * The result has no leading plus sign because it is used as Moodle's
     * username. Every student must enter an international E.164 number, so the
     * same person cannot create distinct accounts by switching between a local
     * and an international representation of the number.
     *
     * @param string $phone User-supplied phone value.
     * @return string Digits-only international number, or an empty string.
     */
    public static function normalise_phone(string $phone): string {
        $arabicdigits = [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];
        $digits = preg_replace('/\D/u', '', strtr($phone, $arabicdigits));

        if (substr($digits, 0, 2) === '00') {
            $digits = substr($digits, 2);
        }

        if (
            strlen($digits) < 8 || strlen($digits) > 15 || !ctype_digit($digits)
                || substr($digits, 0, 1) === '0'
        ) {
            return '';
        }

        return $digits;
    }

    /**
     * Normalises the canonical value posted by the country picker.
     */
    public static function normalise_submitted_phone(string $phone, string $countrycode = ''): string {
        return self::normalise_phone($phone);
    }

    /**
     * Removes exactly one repeated dialling-code prefix for an admin repair.
     */
    public static function remove_repeated_country_code(string $phone, string $countrycode): string {
        $normalised = self::normalise_phone($phone);
        $countrycode = preg_replace('/\D/', '', $countrycode);
        if ($normalised === '' || $countrycode === '' || strlen($countrycode) > 3) {
            return $normalised;
        }
        $duplicatedprefix = $countrycode . $countrycode;
        if (strpos($normalised, $duplicatedprefix) !== 0) {
            return $normalised;
        }
        $candidate = self::normalise_phone(substr($normalised, strlen($countrycode)));
        return $candidate !== '' && strpos($candidate, $countrycode) === 0 ? $candidate : $normalised;
    }

    /**
     * Masks an email address for a non-sensitive reminder banner.
     *
     * @param string $email Email address.
     * @return string Masked email address.
     */
    public static function mask_email(string $email): string {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[0] === '') {
            return '***';
        }

        $localpart = $parts[0];
        $visible = \core_text::substr($localpart, 0, 2);
        return $visible . str_repeat('•', max(1, \core_text::strlen($localpart) - 2)) . '@' . $parts[1];
    }

    /**
     * Masks all but the final four phone digits.
     *
     * @param string $phone Digits-only phone.
     * @return string
     */
    public static function mask_phone(string $phone): string {
        return strlen($phone) <= 4 ? $phone : str_repeat('•', strlen($phone) - 4) . substr($phone, -4);
    }

    /**
     * Generates an unambiguous short code.
     *
     * @param int $length Code length.
     * @return string
     */
    public static function generate_code(int $length = 10): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($index = 0; $index < $length; $index++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $code;
    }

    /**
     * Hashes a one-time secret for storage.
     */
    public static function hash_token(string $token): string {
        return hash('sha256', strtoupper(trim($token)));
    }

    /**
     * Returns JSON-encoded localized country map for the current language.
     *
     * @return string
     */
    public static function localized_countries_json(): string {
        $lang = current_language();
        $countries = get_string_manager()->get_list_of_countries(true, $lang);
        $localized = [];
        foreach ($countries as $iso => $name) {
            $localized[strtolower($iso)] = $name;
        }
        return json_encode($localized, JSON_UNESCAPED_UNICODE);
    }
}
