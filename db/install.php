<?php
// This file is part of Moodle - https://moodle.org/

/** Post-install defaults. @package local_qlogin_shomokh */
defined('MOODLE_INTERNAL') || die();

/** Set explicit defaults for fresh installations. */
function xmldb_local_qlogin_shomokh_install(): void {
    foreach ([
        'enabled' => 1,
        'defaultcountry' => 'sa',
        'requireemail' => 1,
        'requirephone' => 1,
        'graceperioddays' => 30,
        'expiredaction' => 'remind',
        'reminderintervaldays' => 7,
        'maxreminders' => 3,
        'resendcooldown' => 600,
        'authtypes' => 'manual',
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
    ] as $name => $value) {
        set_config($name, $value, 'local_qlogin_shomokh');
    }
    set_config('tokensecret', bin2hex(random_bytes(32)), 'local_qlogin_shomokh');
}
