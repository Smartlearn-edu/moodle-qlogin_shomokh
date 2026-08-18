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
 * Install functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Set explicit defaults for fresh installations.
 */
function xmldb_local_qlogin_shomokh_install(): void {
    foreach (
        [
        'enabled' => 1,
        'defaultcountry' => 'sa',
        'requireemail' => 1,
        'requirephone' => 1,
        'graceperioddays' => 30,
        'expiredaction' => 'remind',
        'reminderintervaldays' => 7,
        'maxreminders' => 3,
        'resendcooldown' => 600,
        'authtypes' => 'manual,email',
        'selfclaimenabled' => 1,
        'recoveryenabled' => 1,
        'recoveryexpiryminutes' => 15,
        'eventretentiondays' => 90,
        'mailprovider' => 'resend',
        'resendfromemail' => '',
        'resendfromname' => 'Shomokh Al-Elm',
        'resendtimeout' => 8,
        'mailmaxattempts' => 5,
        'emailrecoveryenabled' => 1,
        'maillogretentiondays' => 90,
        'legacydefaultcountrycode' => '966',
        ] as $name => $value
    ) {
        set_config($name, $value, 'local_qlogin_shomokh');
    }
    set_config('tokensecret', bin2hex(random_bytes(32)), 'local_qlogin_shomokh');
}
