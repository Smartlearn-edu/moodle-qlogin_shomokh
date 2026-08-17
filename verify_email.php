<?php
// This file is part of Moodle - https://moodle.org/

/** Email-link verification endpoint. @package local_qlogin_shomokh */
require_once('../../config.php');

$userid = optional_param('u', 0, PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/qlogin_shomokh/verify_email.php', ['u' => $userid, 'token' => $token]));
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('verifyemail:title', 'local_qlogin_shomokh'));
if (!$userid) {
    $userid = (int)$DB->get_field('local_qlogin_shomokh_verify', 'userid', [
        'channel' => 'email',
        'tokenhash' => \local_qlogin_shomokh\manager::hash_token($token),
    ]);
}
$verified = $userid && \local_qlogin_shomokh\verification::verify_email($userid, $token);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('verifyemail:title', 'local_qlogin_shomokh'));
echo $OUTPUT->notification(get_string($verified ? 'verifyemail:verified' : 'verifyemail:invalid',
    'local_qlogin_shomokh'), $verified ? 'success' : 'warning');
echo $OUTPUT->single_button(new moodle_url('/local/qlogin_shomokh/index.php'),
    get_string('backtologin', 'local_qlogin_shomokh'), 'get');
echo $OUTPUT->footer();
