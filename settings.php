<?php
// This file is part of Moodle - https://moodle.org/

/** Administration settings. @package local_qlogin_shomokh */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_qlogin_shomokh', get_string('settings', 'local_qlogin_shomokh'));
    $ADMIN->add('localplugins', $settings);
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_qlogin_shomokh_manage',
        get_string('manage:title', 'local_qlogin_shomokh'),
        new moodle_url('/local/qlogin_shomokh/manage.php'),
        'local/qlogin_shomokh:manage'
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_qlogin_shomokh_health',
        get_string('health:title', 'local_qlogin_shomokh'),
        new moodle_url('/local/qlogin_shomokh/health.php'),
        'local/qlogin_shomokh:manage'
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_qlogin_shomokh_migration',
        get_string('migration:title', 'local_qlogin_shomokh'),
        new moodle_url('/local/qlogin_shomokh/migration.php'),
        'local/qlogin_shomokh:manage'
    ));
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading('local_qlogin_shomokh/verificationheading',
            get_string('settings:verification', 'local_qlogin_shomokh'),
            get_string('settings:verification_desc', 'local_qlogin_shomokh')));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/defaultcountry',
            get_string('defaultcountry', 'local_qlogin_shomokh'),
            get_string('defaultcountry_desc', 'local_qlogin_shomokh'), 'sa', PARAM_ALPHA));
        $settings->add(new admin_setting_configcheckbox('local_qlogin_shomokh/enabled',
            get_string('enabled', 'local_qlogin_shomokh'), get_string('enabled_desc', 'local_qlogin_shomokh'), 1));
        $settings->add(new admin_setting_configcheckbox('local_qlogin_shomokh/requireemail',
            get_string('requireemail', 'local_qlogin_shomokh'), get_string('requireemail_desc', 'local_qlogin_shomokh'), 1));
        $settings->add(new admin_setting_configcheckbox('local_qlogin_shomokh/requirephone',
            get_string('requirephone', 'local_qlogin_shomokh'), get_string('requirephone_desc', 'local_qlogin_shomokh'), 1));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/graceperioddays',
            get_string('graceperioddays', 'local_qlogin_shomokh'), get_string('graceperioddays_desc', 'local_qlogin_shomokh'),
            30, PARAM_INT));
        $settings->add(new admin_setting_configselect('local_qlogin_shomokh/expiredaction',
            get_string('expiredaction', 'local_qlogin_shomokh'), get_string('expiredaction_desc', 'local_qlogin_shomokh'),
            'remind', [
                'remind' => get_string('expiredaction:remind', 'local_qlogin_shomokh'),
                'courses' => get_string('expiredaction:courses', 'local_qlogin_shomokh'),
                'suspend' => get_string('expiredaction:suspend', 'local_qlogin_shomokh'),
            ]));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/reminderintervaldays',
            get_string('reminderintervaldays', 'local_qlogin_shomokh'),
            get_string('reminderintervaldays_desc', 'local_qlogin_shomokh'), 7, PARAM_INT));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/maxreminders',
            get_string('maxreminders', 'local_qlogin_shomokh'), get_string('maxreminders_desc', 'local_qlogin_shomokh'),
            3, PARAM_INT));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/resendcooldown',
            get_string('resendcooldownsetting', 'local_qlogin_shomokh'),
            get_string('resendcooldown_desc', 'local_qlogin_shomokh'), 600, PARAM_INT));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/authtypes',
            get_string('authtypes', 'local_qlogin_shomokh'), get_string('authtypes_desc', 'local_qlogin_shomokh'),
            'manual, email', PARAM_NOTAGS));
        $settings->add(new admin_setting_configcheckbox('local_qlogin_shomokh/selfclaimenabled',
            get_string('selfclaimenabled', 'local_qlogin_shomokh'),
            get_string('selfclaimenabled_desc', 'local_qlogin_shomokh'), 1));

        $settings->add(new admin_setting_heading('local_qlogin_shomokh/mailheading',
            get_string('settings:mail', 'local_qlogin_shomokh'),
            get_string('settings:mail_desc', 'local_qlogin_shomokh')));
        $settings->add(new admin_setting_configselect('local_qlogin_shomokh/mailprovider',
            get_string('mailprovider', 'local_qlogin_shomokh'), get_string('mailprovider_desc', 'local_qlogin_shomokh'),
            'resend', [
                'resend' => get_string('mailprovider:resend', 'local_qlogin_shomokh'),
                'moodle' => get_string('mailprovider:moodle', 'local_qlogin_shomokh'),
            ]));
        $settings->add(new admin_setting_configpasswordunmask('local_qlogin_shomokh/resendapikey',
            get_string('resendapikey', 'local_qlogin_shomokh'), get_string('resendapikey_desc', 'local_qlogin_shomokh'),
            ''));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/resendfromemail',
            get_string('resendfromemail', 'local_qlogin_shomokh'),
            get_string('resendfromemail_desc', 'local_qlogin_shomokh'), '', PARAM_EMAIL));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/resendfromname',
            get_string('resendfromname', 'local_qlogin_shomokh'),
            get_string('resendfromname_desc', 'local_qlogin_shomokh'), 'Shomokh Al-Elm', PARAM_TEXT));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/resendtimeout',
            get_string('resendtimeout', 'local_qlogin_shomokh'), get_string('resendtimeout_desc', 'local_qlogin_shomokh'),
            8, PARAM_INT));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/mailmaxattempts',
            get_string('mailmaxattempts', 'local_qlogin_shomokh'),
            get_string('mailmaxattempts_desc', 'local_qlogin_shomokh'), 5, PARAM_INT));
        $settings->add(new admin_setting_configcheckbox('local_qlogin_shomokh/emailrecoveryenabled',
            get_string('emailrecoveryenabled', 'local_qlogin_shomokh'),
            get_string('emailrecoveryenabled_desc', 'local_qlogin_shomokh'), 1));
        $settings->add(new admin_setting_configpasswordunmask('local_qlogin_shomokh/resendwebhooksecret',
            get_string('resendwebhooksecret', 'local_qlogin_shomokh'),
            get_string('resendwebhooksecret_desc', 'local_qlogin_shomokh'), ''));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/maillogretentiondays',
            get_string('maillogretentiondays', 'local_qlogin_shomokh'),
            get_string('maillogretentiondays_desc', 'local_qlogin_shomokh'), 90, PARAM_INT));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/legacydefaultcountrycode',
            get_string('legacydefaultcountrycode', 'local_qlogin_shomokh'),
            get_string('legacydefaultcountrycode_desc', 'local_qlogin_shomokh'), '966', PARAM_INT));

        $settings->add(new admin_setting_heading('local_qlogin_shomokh/whatsappheading',
            get_string('settings:whatsapp', 'local_qlogin_shomokh'),
            get_string('settings:whatsapp_desc', 'local_qlogin_shomokh')));
        $settings->add(new \local_qlogin_shomokh\admin_setting_business_number('local_qlogin_shomokh/businessnumber',
            get_string('businessnumber', 'local_qlogin_shomokh'), get_string('businessnumber_desc', 'local_qlogin_shomokh'),
            '', PARAM_NOTAGS));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/businessphonenumberid',
            get_string('businessphonenumberid', 'local_qlogin_shomokh'),
            get_string('businessphonenumberid_desc', 'local_qlogin_shomokh'), '', PARAM_ALPHANUM));
        $settings->add(new admin_setting_configpasswordunmask('local_qlogin_shomokh/webhookverifytoken',
            get_string('webhookverifytoken', 'local_qlogin_shomokh'),
            get_string('webhookverifytoken_desc', 'local_qlogin_shomokh'), ''));
        $settings->add(new admin_setting_configpasswordunmask('local_qlogin_shomokh/webhookappsecret',
            get_string('webhookappsecret', 'local_qlogin_shomokh'),
            get_string('webhookappsecret_desc', 'local_qlogin_shomokh'), ''));
        $settings->add(new admin_setting_configcheckbox('local_qlogin_shomokh/recoveryenabled',
            get_string('recoveryenabled', 'local_qlogin_shomokh'),
            get_string('recoveryenabled_desc', 'local_qlogin_shomokh'), 1));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/recoveryexpiryminutes',
            get_string('recoveryexpiryminutes', 'local_qlogin_shomokh'),
            get_string('recoveryexpiryminutes_desc', 'local_qlogin_shomokh'), 15, PARAM_INT));
        $settings->add(new admin_setting_configtext('local_qlogin_shomokh/eventretentiondays',
            get_string('eventretentiondays', 'local_qlogin_shomokh'),
            get_string('eventretentiondays_desc', 'local_qlogin_shomokh'), 90, PARAM_INT));
    }
}
