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
 * Verify functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

require_login();
if (isguestuser()) {
    throw new require_login_exception('guestsarenotallowed');
}
$context = context_system::instance();
if (is_siteadmin($USER->id) || has_capability('local/qlogin_shomokh:manage', $context)) {
    redirect(new moodle_url('/local/qlogin_shomokh/health.php'));
}
$url = new moodle_url('/local/qlogin_shomokh/verify.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('verify:title', 'local_qlogin_shomokh'));
$PAGE->set_heading(format_string($SITE->fullname));
$assetversion = (int)get_config('local_qlogin_shomokh', 'version');
$PAGE->requires->js(new moodle_url('/local/qlogin_shomokh/qlogin_verify.js', ['v' => $assetversion]));

$user = $DB->get_record('user', ['id' => $USER->id], '*', MUST_EXIST);
$records = \local_qlogin_shomokh\verification::ensure_for_user($user);
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    try {
        if ($action === 'resendemail') {
            if (\local_qlogin_shomokh\verification::channel_complete((int)$USER->id, 'email')) {
                \core\notification::success(get_string('emailunchanged', 'local_qlogin_shomokh'));
            } else {
                $emailrecord = \local_qlogin_shomokh\verification::issue_email($user);
                \core\notification::success(get_string($emailrecord->mailstatus === 'sent'
                    ? 'mail:sent' : 'mail:retryqueued', 'local_qlogin_shomokh'));
            }
        } else if ($action === 'newphonecode') {
            if (\local_qlogin_shomokh\migration::phone_for_user($user) === '') {
                redirect(new moodle_url('/local/qlogin_shomokh/link_existing.php'));
            }
            if (\local_qlogin_shomokh\verification::channel_complete((int)$USER->id, 'phone')) {
                \core\notification::success(get_string('phonealreadyverified', 'local_qlogin_shomokh'));
            } else {
                // When this browser cannot read the existing code, replacing it
                // is the only safe way to prepare the WhatsApp deep-link.
                $sessioncode = $SESSION->local_qlogin_shomokh_codes[$USER->id] ?? '';
                \local_qlogin_shomokh\verification::issue_phone($user, $sessioncode !== '');
                \core\notification::success(get_string('phonecodecreated', 'local_qlogin_shomokh'));
            }
        } else {
            throw new moodle_exception('invalidaction', 'local_qlogin_shomokh');
        }
    } catch (moodle_exception $exception) {
        \core\notification::error($exception->getMessage());
    }
    redirect($url);
}

$emailform = new \local_qlogin_shomokh\form\email_form($url);
if ($data = $emailform->get_data()) {
    $email = \local_qlogin_shomokh\manager::normalise_email($data->email);
    $emailsql = $DB->sql_equal('email', ':email', false);
    $duplicate = $DB->record_exists_select(
        'user',
        "$emailsql AND id <> :userid AND deleted = :deleted",
        ['email' => $email, 'userid' => $user->id, 'deleted' => 0]
    );
    if ($email === '' || $duplicate) {
        \core\notification::error(get_string($email === '' ? 'error:email' : 'error:emailexists', 'local_qlogin_shomokh'));
    } else {
        $changed = $email !== $user->email;
        if (!$changed) {
            $message = \local_qlogin_shomokh\verification::channel_complete((int)$USER->id, 'email')
                ? 'emailunchanged' : 'emailpendingunchanged';
            \core\notification::success(get_string($message, 'local_qlogin_shomokh'));
            redirect($url);
        }
        $user->email = $email;
        user_update_user($user, false, true);
        $USER->email = $email;
        $user = $DB->get_record('user', ['id' => $USER->id], '*', MUST_EXIST);
        \local_qlogin_shomokh\verification::ensure_channel($user, \local_qlogin_shomokh\verification::EMAIL);
        try {
            $emailrecord = \local_qlogin_shomokh\verification::issue_email($user, false);
            \core\notification::success(get_string($emailrecord->mailstatus === 'sent'
                ? 'mail:sent' : 'mail:retryqueued', 'local_qlogin_shomokh'));
        } catch (moodle_exception $exception) {
            \core\notification::error($exception->getMessage());
        }
    }
    redirect($url);
}
$emailform->set_data(['email' => $user->email]);

$records = \local_qlogin_shomokh\verification::ensure_for_user($user);
$currentphone = \local_qlogin_shomokh\migration::phone_for_user($user);
$hasphone = $currentphone !== '';
$phonecomplete = !isset($records['phone'])
    || \local_qlogin_shomokh\verification::record_complete($records['phone']);
$phonecode = '';
$whatsappurl = null;
if (isset($records['phone']) && !$phonecomplete && $hasphone) {
    [$records['phone'], $phonecode] = \local_qlogin_shomokh\verification::phone_code($user);
    if ($phonecode !== '') {
        $whatsappurl = \local_qlogin_shomokh\verification::whatsapp_url($phonecode);
    }
}

echo $OUTPUT->header();
echo html_writer::start_tag('main', [
    'id' => 'qlogin-verification',
    'class' => 'local-qlogin-verification',
    'data-phone-pending' => $phonecomplete ? '0' : '1',
]);
echo $OUTPUT->heading(get_string('verify:title', 'local_qlogin_shomokh'));
$configuredgrace = get_config('local_qlogin_shomokh', 'graceperioddays');
$gracedays = $configuredgrace === false ? 30 : max(1, min(365, (int)$configuredgrace));
echo html_writer::tag(
    'p',
    get_string('verify:intro', 'local_qlogin_shomokh', $gracedays),
    ['class' => 'local-qlogin-verification__intro']
);

if (empty($records)) {
    echo $OUTPUT->notification(get_string('verify:notrequired', 'local_qlogin_shomokh'), 'info');
}

// WhatsApp is first because it is the primary identity-verification action.
if (isset($records['phone'])) {
    $record = $records['phone'];
    echo html_writer::start_tag('section', ['class' => 'local-qlogin-verify-card', 'aria-labelledby' => 'phone-heading']);
    echo html_writer::tag('h2', get_string('verify:phoneheading', 'local_qlogin_shomokh'), ['id' => 'phone-heading']);
    if ($phonecomplete) {
        echo $OUTPUT->notification(get_string(
            'verify:phonecomplete',
            'local_qlogin_shomokh',
            \local_qlogin_shomokh\manager::mask_phone($record->target)
        ), 'success');
    } else if (!$hasphone) {
        echo $OUTPUT->notification(get_string('verify:phonemissing', 'local_qlogin_shomokh'), 'warning');
        echo html_writer::div(html_writer::link(
            new moodle_url('/local/qlogin_shomokh/link_existing.php'),
            get_string('claim:bannerbutton', 'local_qlogin_shomokh'),
            ['class' => 'btn btn-primary']
        ), 'local-qlogin-verification__primary');
    } else {
        echo $OUTPUT->notification(get_string(
            'verify:phonepending',
            'local_qlogin_shomokh',
            \local_qlogin_shomokh\manager::mask_phone($record->target)
        ), 'info');
        echo html_writer::alist([
            get_string('verify:stepopen', 'local_qlogin_shomokh'),
            get_string('verify:stepsend', 'local_qlogin_shomokh'),
            get_string('verify:stepreturn', 'local_qlogin_shomokh'),
        ], ['class' => 'local-qlogin-verification__steps']);

        if ($phonecode === '') {
            echo $OUTPUT->notification(get_string('verify:codeinanotherbrowser', 'local_qlogin_shomokh'), 'info');
            $newcode = new moodle_url($url, ['action' => 'newphonecode', 'sesskey' => sesskey()]);
            echo html_writer::div(
                $OUTPUT->single_button(
                    $newcode,
                    get_string('verify:preparewhatsapp', 'local_qlogin_shomokh'),
                    'post'
                ),
                'local-qlogin-verification__primary'
            );
        } else if ($whatsappurl) {
            echo html_writer::div(html_writer::link(
                $whatsappurl,
                get_string('verify:sendwhatsapp', 'local_qlogin_shomokh'),
                [
                    'class' => 'btn btn-success btn-lg local-qlogin-whatsapp-primary',
                    'target' => '_blank',
                    'rel' => 'noopener',
                    'data-qlogin-whatsapp' => '1',
                ]
            ), 'local-qlogin-verification__primary');

            $message = 'SHOMOKH VERIFY ' . $phonecode;
            $businessnumber = \local_qlogin_shomokh\manager::normalise_phone(
                (string)get_config('local_qlogin_shomokh', 'businessnumber')
            );
            echo html_writer::start_tag('details', ['class' => 'local-qlogin-verification__fallback']);
            echo html_writer::tag('summary', get_string('verify:manualfallback', 'local_qlogin_shomokh'));
            echo html_writer::tag('p', get_string('verify:manualhelp', 'local_qlogin_shomokh', '+' . $businessnumber));
            echo html_writer::tag('code', s($message), ['class' => 'local-qlogin-code', 'dir' => 'ltr']);
            echo html_writer::end_tag('details');

            echo html_writer::start_tag('details', ['class' => 'local-qlogin-verification__trouble']);
            echo html_writer::tag('summary', get_string('verify:trouble', 'local_qlogin_shomokh'));
            $remaining = \local_qlogin_shomokh\verification::cooldown_remaining($record);
            if ($remaining > 0) {
                echo html_writer::tag(
                    'p',
                    get_string('resendavailablein', 'local_qlogin_shomokh', $remaining),
                    ['class' => 'text-muted']
                );
            } else {
                $newcode = new moodle_url($url, ['action' => 'newphonecode', 'sesskey' => sesskey()]);
                echo $OUTPUT->single_button($newcode, get_string('verify:replacecode', 'local_qlogin_shomokh'), 'post');
            }
            echo html_writer::end_tag('details');
        } else {
            echo $OUTPUT->notification(get_string('verify:whatsappnotconfigured', 'local_qlogin_shomokh'), 'warning');
        }
    }
    if ($hasphone) {
        echo html_writer::div(html_writer::link(
            new moodle_url('/local/qlogin_shomokh/link_existing.php'),
            get_string('claim:changephone', 'local_qlogin_shomokh'),
            ['class' => 'btn btn-outline-secondary']
        ), 'mt-3');
    }
    echo html_writer::end_tag('section');
}

if (isset($records['email'])) {
    $record = $records['email'];
    $complete = \local_qlogin_shomokh\verification::record_complete($record);
    echo html_writer::start_tag('section', ['class' => 'local-qlogin-verify-card', 'aria-labelledby' => 'email-heading']);
    echo html_writer::tag('h2', get_string('verify:emailheading', 'local_qlogin_shomokh'), ['id' => 'email-heading']);
    echo $OUTPUT->notification(get_string(
        $complete ? 'verify:emailcomplete' : 'verify:emailpending',
        'local_qlogin_shomokh',
        \local_qlogin_shomokh\manager::mask_email($record->target)
    ), $complete ? 'success' : 'info');
    echo html_writer::tag('p', get_string('verify:emailhelp', 'local_qlogin_shomokh'));
    $emailform->display();
    if (!$complete) {
        $remaining = \local_qlogin_shomokh\verification::cooldown_remaining($record);
        if ($remaining > 0) {
            echo html_writer::tag(
                'p',
                get_string('resendavailablein', 'local_qlogin_shomokh', $remaining),
                ['class' => 'text-muted']
            );
        } else {
            $resend = new moodle_url($url, ['action' => 'resendemail', 'sesskey' => sesskey()]);
            echo $OUTPUT->single_button($resend, get_string('emailpage:resend', 'local_qlogin_shomokh'), 'post');
        }
    }
    echo html_writer::end_tag('section');
}
echo html_writer::div(
    $OUTPUT->continue_button(new moodle_url('/my/')),
    'local-qlogin-verification__continue'
);
echo html_writer::end_tag('main');
echo $OUTPUT->footer();
