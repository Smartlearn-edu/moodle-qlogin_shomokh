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
echo $OUTPUT->notification(get_string(
    $verified ? 'verifyemail:verified' : 'verifyemail:invalid',
    'local_qlogin_shomokh'
), $verified ? 'success' : 'warning');
echo $OUTPUT->single_button(
    new moodle_url('/local/qlogin_shomokh/index.php'),
    get_string('backtologin', 'local_qlogin_shomokh'),
    'get'
);
echo $OUTPUT->footer();
