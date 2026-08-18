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
 * Main login and registration gateway.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once('../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/authlib.php');

$context = context_system::instance();
$url = new moodle_url('/local/qlogin_shomokh/index.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('pluginname', 'local_qlogin_shomokh'));
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

if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/'));
}

$action = optional_param('action', 'login', PARAM_ALPHA);
$action = in_array($action, ['login', 'register'], true) ? $action : 'login';
$registering = $action === 'register';
$form = new \local_qlogin_shomokh\form\auth_form($url, ['action' => $action]);
$form->set_data(['action' => $action]);

if ($data = $form->get_data()) {
    if ($data->website !== '') {
        redirect(new moodle_url('/'));
    }
    if ($data->action === 'login') {
        $identifier = trim((string)$data->identifier);
        $loginusername = \local_qlogin_shomokh\account_link::resolve_username($identifier);
        $existinguser = $loginusername === null ? false : $DB->get_record('user', [
            'username' => $loginusername,
            'deleted' => 0,
        ], 'id, suspended');
        try {
            $user = $loginusername === null ? false : authenticate_user_login($loginusername, $data->password);
        } catch (Throwable $exception) {
            $user = false;
        }
        if ($user && empty($user->deleted) && empty($user->suspended)) {
            \local_qlogin_shomokh\verification::bootstrap_existing_user($user);
            complete_user_login($user);
            redirect(new moodle_url('/my/'));
        }
        if (\local_qlogin_shomokh\account_link::email_is_ambiguous($identifier)) {
            \core\notification::error(get_string('error:emailambiguous', 'local_qlogin_shomokh'));
        } else if (!$existinguser) {
            \core\notification::error(get_string('error:usernotfound', 'local_qlogin_shomokh'));
        } else if ($existinguser->suspended) {
            \core\notification::error(get_string('error:accountunavailable', 'local_qlogin_shomokh'));
        } else {
            \core\notification::error(get_string('error:wrongpassword', 'local_qlogin_shomokh'));
        }
    } else {
        $phone = \local_qlogin_shomokh\manager::normalise_submitted_phone(
            (string)$data->phone,
            (string)($data->phonecountrycode ?? '')
        );
        $nameparts = preg_split('/\s+/u', trim($data->fullname), 2);
        $newuser = (object)[
            'username' => $phone,
            'password' => hash_internal_user_password($data->password),
            'email' => \local_qlogin_shomokh\manager::normalise_email($data->email),
            'firstname' => $nameparts[0],
            'lastname' => $nameparts[1] ?? '-',
            'confirmed' => 1,
            'auth' => 'manual',
            'mnethostid' => $CFG->mnet_localhost_id,
            'lang' => current_language(),
        ];
        $phonelock = null;
        try {
            $lockfactory = \core\lock\lock_config::get_lock_factory('local_qlogin_shomokh');
            $phonelock = $lockfactory->get_lock('phone_' . hash('sha256', $phone), 5);
            if (!$phonelock) {
                throw new moodle_exception('claim:busy', 'local_qlogin_shomokh');
            }
            // Repeat the validation while holding the phone lock. The form
            // check gives fast feedback; this check closes concurrent races.
            if (\local_qlogin_shomokh\migration::phone_in_use($phone)) {
                throw new moodle_exception('error:userexists', 'local_qlogin_shomokh');
            }
            try {
                $transaction = $DB->start_delegated_transaction();
                // Trigger Moodle's standard user-created event for integrations and enrolment automation.
                $newuser->id = user_create_user($newuser, false, true);
                $user = $DB->get_record('user', ['id' => $newuser->id], '*', MUST_EXIST);
                \local_qlogin_shomokh\verification::ensure_for_user($user);
                $transaction->allow_commit();
            } catch (Throwable $exception) {
                if (isset($transaction)) {
                    $transaction->rollback($exception);
                }
                throw $exception;
            }
            $phonelock->release();
            $phonelock = null;
            if (
                \local_qlogin_shomokh\verification::available()
                    && in_array('email', \local_qlogin_shomokh\verification::required_channels(), true)
            ) {
                try {
                    $emailrecord = \local_qlogin_shomokh\verification::issue_email($user, false);
                    \core\notification::success(get_string($emailrecord->mailstatus === 'sent'
                        ? 'mail:sent' : 'mail:retryqueued', 'local_qlogin_shomokh'));
                } catch (Throwable $mailexception) {
                    debugging($mailexception->getMessage(), DEBUG_DEVELOPER);
                    \core\notification::warning(get_string('mail:sendfailed', 'local_qlogin_shomokh'));
                }
            }
            complete_user_login($user);
            redirect(
                new moodle_url('/local/qlogin_shomokh/verify.php'),
                get_string('accountcreated', 'local_qlogin_shomokh')
            );
        } catch (moodle_exception $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
            $message = in_array($exception->errorcode, ['error:userexists', 'claim:busy'], true)
                ? $exception->getMessage()
                : get_string('error:createaccount', 'local_qlogin_shomokh');
            \core\notification::error($message);
        } catch (Throwable $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
            \core\notification::error(get_string('error:createaccount', 'local_qlogin_shomokh'));
        } finally {
            if ($phonelock) {
                $phonelock->release();
            }
        }
    }
}

$logourl = $OUTPUT->get_logo_url();
$sitename = format_string($SITE->fullname);
$defaultcountry = strtolower((string)(get_config('local_qlogin_shomokh', 'defaultcountry') ?: 'sa'));
if (!preg_match('/^[a-z]{2}$/', $defaultcountry)) {
    $defaultcountry = 'sa';
}
$gracedays = max(1, min(365, (int)(get_config('local_qlogin_shomokh', 'graceperioddays') ?: 30)));
$initialtitle = get_string($registering ? 'registertitle' : 'logintitle', 'local_qlogin_shomokh');
$initialsubtitle = $registering
    ? get_string('registersubtitle', 'local_qlogin_shomokh', $gracedays)
    : get_string('loginsubtitle', 'local_qlogin_shomokh');

echo $OUTPUT->header();

$logocontent = '';
if ($logourl) {
    $logocontent = html_writer::empty_tag('img', ['src' => $logourl, 'alt' => $sitename]);
} else {
    $logocontent = html_writer::tag('div', s($sitename), ['class' => 'site-name']);
}

$loginurl = (new moodle_url($url, ['action' => 'login']))->out(false);
$registerurl = (new moodle_url($url, ['action' => 'register']))->out(false);

$loginactive = $registering ? '' : ' active';
$registeractive = $registering ? ' active' : '';

$tabshtml = html_writer::start_div('auth-tabs', [
    'role' => 'tablist',
    'aria-label' => get_string('authenticationtabs', 'local_qlogin_shomokh'),
]);
$tabshtml .= html_writer::link(
    $loginurl,
    get_string('login', 'local_qlogin_shomokh'),
    [
        'class' => 'tab-btn' . $loginactive,
        'id' => 'btn-login',
        'role' => 'tab',
        'aria-selected' => $registering ? 'false' : 'true',
    ]
);
$tabshtml .= html_writer::link(
    $registerurl,
    get_string('register', 'local_qlogin_shomokh'),
    [
        'class' => 'tab-btn' . $registeractive,
        'id' => 'btn-register',
        'role' => 'tab',
        'aria-selected' => $registering ? 'true' : 'false',
    ]
);
$tabshtml .= html_writer::end_div();

$wrapperattrs = [
    'id' => 'qlogin-wrapper',
    'class' => 'notranslate',
    'translate' => 'no',
    'data-mode' => $registering ? 'register' : 'login',
    'data-default-country' => $defaultcountry,
    'data-login-title' => get_string('logintitle', 'local_qlogin_shomokh'),
    'data-login-subtitle' => get_string('loginsubtitle', 'local_qlogin_shomokh'),
    'data-login-button' => get_string('submit_login', 'local_qlogin_shomokh'),
    'data-register-title' => get_string('registertitle', 'local_qlogin_shomokh'),
    'data-register-subtitle' => get_string('registersubtitle', 'local_qlogin_shomokh', $gracedays),
    'data-register-button' => get_string('submit_register', 'local_qlogin_shomokh'),
    'data-show-password' => get_string('showpassword', 'local_qlogin_shomokh'),
    'data-hide-password' => get_string('hidepassword', 'local_qlogin_shomokh'),
    'data-localized-countries' => \local_qlogin_shomokh\manager::localized_countries_json(),
];

echo html_writer::start_div('notranslate', $wrapperattrs);
echo html_writer::start_div('qlogin-card');
echo html_writer::tag('div', $logocontent, ['class' => 'qlogin-logo']);
echo $tabshtml;
echo html_writer::tag('h2', s($initialtitle), ['class' => 'form-title', 'id' => 'form-title']);
echo html_writer::tag('p', s($initialsubtitle), ['class' => 'form-subtitle', 'id' => 'form-subtitle']);

$form->display();

$forgotpassclass = 'forgot-password-link' . ($registering ? ' d-none' : '');
$recoverurl = (new moodle_url('/local/qlogin_shomokh/recover.php'))->out(false);
echo html_writer::start_div($forgotpassclass, ['id' => 'forgot-password-link']);
echo html_writer::link($recoverurl, get_string('forgotpassword', 'local_qlogin_shomokh'));
echo html_writer::end_div();

if (\local_qlogin_shomokh\migration::self_claim_available()) {
    $claimurl = (new moodle_url('/local/qlogin_shomokh/link_existing.php'))->out(false);
    echo html_writer::start_div('existing-account-link');
    echo html_writer::link($claimurl, get_string('claim:entrylink', 'local_qlogin_shomokh'));
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
