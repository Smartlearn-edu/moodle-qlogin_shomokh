<?php
// This file is part of Moodle - https://moodle.org/

/** Single-use email password reset endpoint. @package local_qlogin_shomokh */
require_once('../../config.php');

$token = required_param('token', PARAM_ALPHANUM);
$context = context_system::instance();
$url = new moodle_url('/local/qlogin_shomokh/reset_email.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('recovery:resettitle', 'local_qlogin_shomokh'));
$assetversion = (int)get_config('local_qlogin_shomokh', 'version');
$PAGE->requires->css(new moodle_url('/local/qlogin_shomokh/qlogin_styles.css', ['v' => $assetversion]));
$PAGE->requires->js(new moodle_url('/local/qlogin_shomokh/qlogin_phone.js', ['v' => $assetversion]));
header('Referrer-Policy: no-referrer');

$record = \local_qlogin_shomokh\email_recovery::find($token);
$form = new \local_qlogin_shomokh\form\email_reset_form($url, ['token' => $token]);
if ($record && ($data = $form->get_data())) {
    $resetuserid = (int)$record->userid;
    if (\local_qlogin_shomokh\email_recovery::consume($data->token, $data->password)) {
        $resetuser = $DB->get_record('user', ['id' => $resetuserid, 'deleted' => 0]);
        $target = $resetuser && \local_qlogin_shomokh\migration::can_self_claim($resetuser)
            ? new moodle_url('/local/qlogin_shomokh/link_existing.php')
            : new moodle_url('/local/qlogin_shomokh/index.php');
        redirect($target,
            get_string('recovery:passwordchanged', 'local_qlogin_shomokh'));
    }
    $record = false;
}

echo $OUTPUT->header();
echo html_writer::start_div('notranslate', [
    'id' => 'qlogin-wrapper',
    'translate' => 'no',
    'data-show-password' => get_string('showpassword', 'local_qlogin_shomokh'),
    'data-hide-password' => get_string('hidepassword', 'local_qlogin_shomokh'),
]);
echo html_writer::start_div('qlogin-card');
echo $OUTPUT->heading(get_string('recovery:resettitle', 'local_qlogin_shomokh'));
if ($record) {
    $form->display();
} else {
    echo $OUTPUT->notification(get_string('recovery:invalidlink', 'local_qlogin_shomokh'), 'warning');
    echo $OUTPUT->single_button(new moodle_url('/local/qlogin_shomokh/recover.php'),
        get_string('forgotpassword', 'local_qlogin_shomokh'), 'get');
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
