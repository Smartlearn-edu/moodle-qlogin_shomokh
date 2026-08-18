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
 * Recover functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once('../../config.php');

$context = context_system::instance();
$url = new moodle_url('/local/qlogin_shomokh/recover.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('recovery:title', 'local_qlogin_shomokh'));
$assetversion = (int)get_config('local_qlogin_shomokh', 'version');
$PAGE->requires->css(new moodle_url('/local/qlogin_shomokh/qlogin_styles.css', ['v' => $assetversion]));
$PAGE->requires->css(new moodle_url(
    '/local/qlogin_shomokh/vendor/intl-tel-input/build/css/intlTelInput.css',
    ['v' => $assetversion]
));
$PAGE->requires->js(new moodle_url(
    '/local/qlogin_shomokh/vendor/intl-tel-input/build/js/intlTelInput.min.js',
    ['v' => $assetversion]
));
$PAGE->requires->js(new moodle_url('/local/qlogin_shomokh/qlogin_phone.js', ['v' => $assetversion]));

$startform = new \local_qlogin_shomokh\form\recovery_start_form($url);
$resetform = new \local_qlogin_shomokh\form\reset_password_form($url);
$emailrecoveryform = new \local_qlogin_shomokh\form\email_recovery_form($url);
$current = \local_qlogin_shomokh\recovery::current();
$recoveryenabled = (bool)get_config('local_qlogin_shomokh', 'recoveryenabled');

if ($recoveryenabled && $current && $current->state === 'verified' && $current->expiresat >= time()) {
    if ($data = $resetform->get_data()) {
        if (\local_qlogin_shomokh\recovery::reset_password($data->password)) {
            redirect(
                new moodle_url('/local/qlogin_shomokh/index.php'),
                get_string('recovery:passwordchanged', 'local_qlogin_shomokh')
            );
        }
        \core\notification::error(get_string('recovery:expired', 'local_qlogin_shomokh'));
    }
} else if ($recoveryenabled && ($data = $startform->get_data())) {
    try {
        $request = \local_qlogin_shomokh\recovery::start(
            \local_qlogin_shomokh\manager::normalise_submitted_phone(
                (string)$data->phone,
                (string)($data->phonecountrycode ?? '')
            )
        );
        if ($request) {
            [$record, $code] = $request;
            $SESSION->local_qlogin_shomokh_recoverycode = $code;
        }
        \core\notification::success(get_string('recovery:genericstarted', 'local_qlogin_shomokh'));
    } catch (moodle_exception $exception) {
        \core\notification::error($exception->getMessage());
    }
    redirect($url);
}

if ($emaildata = $emailrecoveryform->get_data()) {
    $email = \local_qlogin_shomokh\manager::normalise_email($emaildata->recoveryemail);
    if (\local_qlogin_shomokh\email_recovery::available()) {
        \local_qlogin_shomokh\email_recovery::start($email);
        \core\notification::success(get_string('recovery:emailgenericstarted', 'local_qlogin_shomokh'));
    } else {
        \core\notification::error(get_string('recovery:emaildisabled', 'local_qlogin_shomokh'));
    }
    redirect($url);
}

$current = \local_qlogin_shomokh\recovery::current();
$defaultcountry = strtolower((string)(get_config('local_qlogin_shomokh', 'defaultcountry') ?: 'sa'));
if (!preg_match('/^[a-z]{2}$/', $defaultcountry)) {
    $defaultcountry = 'sa';
}
echo $OUTPUT->header();
echo html_writer::start_div('notranslate', [
    'id' => 'qlogin-wrapper',
    'translate' => 'no',
    'data-default-country' => $defaultcountry,
    'data-show-password' => get_string('showpassword', 'local_qlogin_shomokh'),
    'data-hide-password' => get_string('hidepassword', 'local_qlogin_shomokh'),
    'data-localized-countries' => \local_qlogin_shomokh\manager::localized_countries_json(),
]);
echo html_writer::start_div('qlogin-card');
echo $OUTPUT->heading(get_string('recovery:title', 'local_qlogin_shomokh'));
echo $OUTPUT->heading(get_string('recovery:emailtitle', 'local_qlogin_shomokh'), 3);
echo html_writer::tag('p', get_string('recovery:emailintro', 'local_qlogin_shomokh'));
if (\local_qlogin_shomokh\email_recovery::available()) {
    $emailrecoveryform->display();
} else {
    echo $OUTPUT->notification(get_string('recovery:emaildisabled', 'local_qlogin_shomokh'), 'warning');
}
echo html_writer::empty_tag('hr');
echo $OUTPUT->heading(get_string('recovery:whatsapptitle', 'local_qlogin_shomokh'), 3);
if (!$recoveryenabled) {
    echo $OUTPUT->notification(get_string('recovery:disabled', 'local_qlogin_shomokh'), 'warning');
} else if ($current && $current->state === 'verified' && $current->expiresat >= time()) {
    echo $OUTPUT->notification(get_string('recovery:verified', 'local_qlogin_shomokh'), 'success');
    $resetform->display();
} else if (
    $current && $current->state === 'pending' && $current->expiresat >= time()
        && !empty($SESSION->local_qlogin_shomokh_recoverycode)
) {
    $code = $SESSION->local_qlogin_shomokh_recoverycode;
    echo html_writer::tag('p', get_string('recovery:sendmessage', 'local_qlogin_shomokh'));
    echo html_writer::tag('code', s('SHOMOKH RESET ' . $code), ['class' => 'local-qlogin-code']);
    $whatsappurl = \local_qlogin_shomokh\verification::whatsapp_url($code, 'RESET');
    if ($whatsappurl) {
        echo html_writer::div(html_writer::link(
            $whatsappurl,
            get_string('verify:openwhatsapp', 'local_qlogin_shomokh'),
            ['class' => 'btn btn-success', 'target' => '_blank', 'rel' => 'noopener']
        ), 'my-3');
    } else {
        echo $OUTPUT->notification(get_string('verify:whatsappnotconfigured', 'local_qlogin_shomokh'), 'warning');
    }
    echo $OUTPUT->single_button($url, get_string('recovery:check', 'local_qlogin_shomokh'), 'get');
} else {
    echo html_writer::tag('p', get_string('recovery:intro', 'local_qlogin_shomokh'));
    $startform->display();
}
if (\local_qlogin_shomokh\migration::self_claim_available()) {
    echo html_writer::div(html_writer::link(
        new moodle_url('/local/qlogin_shomokh/link_existing.php'),
        get_string('claim:entrylink', 'local_qlogin_shomokh')
    ), 'existing-account-link');
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
