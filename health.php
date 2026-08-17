<?php
// This file is part of Moodle - https://moodle.org/

/** Integration health and administrator-requested mail test. @package local_qlogin_shomokh */
require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/qlogin_shomokh:manage', $context);
$url = new moodle_url('/local/qlogin_shomokh/health.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('health:title', 'local_qlogin_shomokh'));
$PAGE->set_heading(format_string($SITE->fullname));
$assetversion = (int)get_config('local_qlogin_shomokh', 'version');
$PAGE->requires->js(new moodle_url('/local/qlogin_shomokh/qlogin_verify.js', ['v' => $assetversion]));

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'retirelegacy') {
    require_sesskey();
    try {
        $result = \local_qlogin_shomokh\compatibility::retire_legacy();
        \core\notification::success(get_string('health:legacyretired', 'local_qlogin_shomokh', (object)$result));
    } catch (moodle_exception $exception) {
        \core\notification::error($exception->getMessage());
    }
    redirect($url);
}
if ($action === 'newwhatsapptest') {
    require_sesskey();
    \local_qlogin_shomokh\whatsapp_test::issue((int)$USER->id);
    \core\notification::success(get_string('health:whatsapptestcreated', 'local_qlogin_shomokh'));
    redirect($url);
}

$form = new \local_qlogin_shomokh\form\test_email_form($url);
if ($data = $form->get_data()) {
    $result = \local_qlogin_shomokh\mail\service::send_test($data->recipient, (int)$USER->id);
    if ($result->accepted) {
        \core\notification::success(get_string('health:testsent', 'local_qlogin_shomokh'));
    } else {
        \core\notification::error(get_string('health:testfailed', 'local_qlogin_shomokh'));
    }
    redirect($url);
}

$provider = \local_qlogin_shomokh\mail\config::provider();
[$apikey, $keysource] = \local_qlogin_shomokh\mail\config::resend_api_key();
[$webhooksecret] = \local_qlogin_shomokh\mail\config::resend_webhook_secret();
$sender = \local_qlogin_shomokh\manager::normalise_email(
    (string)get_config('local_qlogin_shomokh', 'resendfromemail'));
$legacy = \local_qlogin_shomokh\compatibility::legacy_status();
$whatsappready = \local_qlogin_shomokh\compatibility::unified_whatsapp_ready();
$configuredphoneid = trim((string)get_config('local_qlogin_shomokh', 'businessphonenumberid'));
$lasttask = 0;
if ($DB->get_manager()->table_exists('task_log')) {
    $lasttask = (int)$DB->get_field_sql('SELECT MAX(timestart) FROM {task_log}');
}
$checks = [
    [get_string('health:provider', 'local_qlogin_shomokh'), get_string('mailprovider:' . $provider,
        'local_qlogin_shomokh'), true],
    [get_string('health:keysource', 'local_qlogin_shomokh'), get_string('health:keysource:' . $keysource,
        'local_qlogin_shomokh'), $provider !== 'resend' || $apikey !== ''],
    [get_string('health:sender', 'local_qlogin_shomokh'), $sender === '' ? '-' : s($sender),
        $provider !== 'resend' || $sender !== ''],
    [get_string('health:webhook', 'local_qlogin_shomokh'), $webhooksecret === '' ? '-' : get_string('health:ok',
        'local_qlogin_shomokh'), $webhooksecret !== ''],
    [get_string('health:cron', 'local_qlogin_shomokh'), $lasttask ? userdate($lasttask) : get_string('health:never',
        'local_qlogin_shomokh'), $lasttask && time() - $lasttask < HOURSECS],
    [get_string('health:whatsapp', 'local_qlogin_shomokh'),
        get_string($whatsappready ? 'health:ok' : 'health:whatsappmissing', 'local_qlogin_shomokh'),
        $whatsappready],
    [get_string('health:configuredphoneid', 'local_qlogin_shomokh'),
        $configuredphoneid === '' ? '-' : s($configuredphoneid), $configuredphoneid !== ''],
    [get_string('health:legacyplugin', 'local_qlogin_shomokh'),
        get_string(!$legacy['present'] ? 'health:legacynotpresent'
            : ($legacy['enabled'] ? 'health:legacyenabled' : 'health:legacydisabled'),
            'local_qlogin_shomokh', $legacy['records']),
        !$legacy['enabled']],
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('health:title', 'local_qlogin_shomokh'));
echo html_writer::tag('p', get_string('health:intro', 'local_qlogin_shomokh'));
$checktable = new html_table();
$checktable->head = [get_string('health:check', 'local_qlogin_shomokh'),
    get_string('health:result', 'local_qlogin_shomokh'), get_string('status')];
foreach ($checks as [$label, $value, $okay]) {
    $checktable->data[] = [$label, $value, get_string($okay ? 'health:ok' : 'health:warning',
        'local_qlogin_shomokh')];
}
$checktable->data[] = [get_string('health:webhookurl', 'local_qlogin_shomokh'),
    (new moodle_url('/local/qlogin_shomokh/resend_webhook.php'))->out(false), get_string('health:ok',
        'local_qlogin_shomokh')];
$checktable->data[] = [get_string('health:whatsappwebhookurl', 'local_qlogin_shomokh'),
    (new moodle_url('/local/qlogin_shomokh/webhook.php'))->out(false), get_string('health:ok',
        'local_qlogin_shomokh')];
echo html_writer::table($checktable);

echo $OUTPUT->heading(get_string('health:whatsappstats', 'local_qlogin_shomokh'), 3);
$whatsapptable = new html_table();
$whatsapptable->head = [
    get_string('health:eventtype', 'local_qlogin_shomokh'),
    get_string('status'),
    get_string('health:time', 'local_qlogin_shomokh'),
];
foreach ($DB->get_records('local_qlogin_shomokh_event', [], 'timecreated DESC', '*', 0, 30) as $record) {
    $whatsapptable->data[] = [s($record->eventtype), s($record->status), userdate($record->timecreated)];
}
echo html_writer::table($whatsapptable);

$whatsapptestcode = \local_qlogin_shomokh\whatsapp_test::active_code((int)$USER->id);
$whatsapptesturl = $whatsapptestcode === '' ? null
    : \local_qlogin_shomokh\whatsapp_test::url($whatsapptestcode);
$latestwhatsapptests = $DB->get_records('local_qlogin_shomokh_event', [
    'userid' => $USER->id,
    'eventtype' => 'integrationtest',
], 'timecreated DESC', '*', 0, 1);
$latestwhatsapptest = reset($latestwhatsapptests);

echo html_writer::start_tag('section', [
    'id' => 'qlogin-verification',
    'class' => 'local-qlogin-health-test',
    'data-phone-pending' => $whatsapptesturl ? '1' : '0',
]);
echo $OUTPUT->heading(get_string('health:whatsapptesttitle', 'local_qlogin_shomokh'), 3);
echo html_writer::tag('p', get_string('health:whatsapptestintro', 'local_qlogin_shomokh'));
if ($latestwhatsapptest) {
    $statuskey = 'health:whatsappteststatus:' . $latestwhatsapptest->status;
    if (get_string_manager()->string_exists($statuskey, 'local_qlogin_shomokh')) {
        $statustext = get_string($statuskey, 'local_qlogin_shomokh');
    } else {
        $statustext = s($latestwhatsapptest->status);
    }
    echo $OUTPUT->notification(get_string('health:whatsapptestlast', 'local_qlogin_shomokh', (object)[
        'status' => $statustext,
        'time' => userdate($latestwhatsapptest->timecreated),
    ]), $latestwhatsapptest->status === 'passed' ? 'success' : 'info');
}
if ($whatsapptesturl) {
    echo html_writer::link($whatsapptesturl,
        get_string('health:whatsapptestopen', 'local_qlogin_shomokh'), [
            'class' => 'btn btn-success btn-lg',
            'target' => '_blank',
            'rel' => 'noopener',
            'data-qlogin-whatsapp' => '1',
        ]);
    echo html_writer::start_tag('details', ['class' => 'mt-3']);
    echo html_writer::tag('summary', get_string('verify:manualfallback', 'local_qlogin_shomokh'));
    echo html_writer::tag('code', s('SHOMOKH TEST ' . $whatsapptestcode), [
        'class' => 'local-qlogin-code',
        'dir' => 'ltr',
    ]);
    echo html_writer::end_tag('details');
} else {
    $testurl = new moodle_url($url, ['action' => 'newwhatsapptest', 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($testurl, get_string('health:whatsappteststart', 'local_qlogin_shomokh'), 'post');
}
echo html_writer::end_tag('section');

if ($legacy['present']) {
    echo $OUTPUT->heading(get_string('health:legacytitle', 'local_qlogin_shomokh'), 3);
    echo html_writer::tag('p', get_string('health:legacyintro', 'local_qlogin_shomokh'));
    $retireurl = new moodle_url($url, ['action' => 'retirelegacy', 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($retireurl, get_string('health:legacyretire', 'local_qlogin_shomokh'), 'post');
}

echo $OUTPUT->heading(get_string('health:testtitle', 'local_qlogin_shomokh'), 3);
echo html_writer::tag('p', get_string('health:testintro', 'local_qlogin_shomokh'));
$form->display();

echo $OUTPUT->heading(get_string('health:mailstats', 'local_qlogin_shomokh'), 3);
$logtable = new html_table();
$logtable->head = [
    get_string('health:purpose', 'local_qlogin_shomokh'),
    get_string('health:recipient', 'local_qlogin_shomokh'),
    get_string('health:provider', 'local_qlogin_shomokh'),
    get_string('status'),
    get_string('health:attempts', 'local_qlogin_shomokh'),
    get_string('health:time', 'local_qlogin_shomokh'),
];
foreach ($DB->get_records('local_qlogin_shomokh_mail', [], 'timecreated DESC', '*', 0, 50) as $record) {
    $logtable->data[] = [s($record->purpose), s($record->recipienthint), s($record->provider), s($record->status),
        (int)$record->attempts, userdate($record->timemodified)];
}
echo html_writer::table($logtable);
echo $OUTPUT->footer();
