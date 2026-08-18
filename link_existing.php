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
 * Link existing functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once('../../config.php');
require_once($CFG->libdir . '/authlib.php');

$context = context_system::instance();
$url = new moodle_url('/local/qlogin_shomokh/link_existing.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('login');
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

if (!\local_qlogin_shomokh\migration::self_claim_available()) {
    redirect(
        new moodle_url('/local/qlogin_shomokh/index.php'),
        get_string('claim:disabled', 'local_qlogin_shomokh'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$authenticated = isloggedin() && !isguestuser();
if (
    $authenticated && (is_siteadmin($USER->id)
        || has_capability('local/qlogin_shomokh:manage', $context))
) {
    redirect(new moodle_url('/local/qlogin_shomokh/health.php'));
}
$currentphone = $authenticated ? \local_qlogin_shomokh\migration::phone_for_user($USER) : '';
$changing = $authenticated && $currentphone !== '';
$PAGE->set_title(get_string($changing ? 'claim:changetitle' : 'claim:title', 'local_qlogin_shomokh'));

$form = new \local_qlogin_shomokh\form\link_existing_form($url, [
    'authenticated' => $authenticated,
    'changing' => $changing,
]);
if ($data = $form->get_data()) {
    if ((string)$data->website !== '') {
        redirect(new moodle_url('/'));
    }
    $claimuser = $authenticated ? $USER : \local_qlogin_shomokh\account_link::authenticate(
        (string)$data->identifier,
        (string)$data->password
    );
    if (!$claimuser) {
        \core\notification::error(get_string('claim:invalidcredentials', 'local_qlogin_shomokh'));
    } else if (
        $changing && !\local_qlogin_shomokh\account_link::reauthenticate(
            $claimuser,
            (string)$data->password
        )
    ) {
        \core\notification::error(get_string('claim:wrongpassword', 'local_qlogin_shomokh'));
    } else {
        try {
            $result = \local_qlogin_shomokh\migration::set_phone(
                $claimuser,
                \local_qlogin_shomokh\manager::normalise_submitted_phone(
                    (string)$data->phone,
                    (string)($data->phonecountrycode ?? '')
                ),
                $changing
            );
            if (!$authenticated) {
                complete_user_login($claimuser);
            }
            $claimuser = $DB->get_record('user', ['id' => $claimuser->id], '*', MUST_EXIST);
            \local_qlogin_shomokh\verification::ensure_for_user($claimuser);
            redirect(
                new moodle_url('/local/qlogin_shomokh/verify.php'),
                get_string(
                    $result === 'created'
                    ? ($changing ? 'claim:changesuccess' : 'claim:success')
                    : 'claim:alreadylinked',
                    'local_qlogin_shomokh'
                )
            );
        } catch (moodle_exception $exception) {
            \core\notification::error($exception->getMessage());
        } catch (Throwable $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
            \core\notification::error(get_string('claim:failed', 'local_qlogin_shomokh'));
        }
    }
}

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
echo $OUTPUT->heading(get_string($changing ? 'claim:changetitle' : 'claim:title', 'local_qlogin_shomokh'));
echo html_writer::tag('p', get_string(
    $changing ? 'claim:changeintro'
    : ($authenticated ? 'claim:introloggedin' : 'claim:intro'),
    'local_qlogin_shomokh'
));
$form->display();
if (!$authenticated) {
    echo html_writer::div(html_writer::link(
        new moodle_url('/local/qlogin_shomokh/recover.php'),
        get_string('claim:forgotpassword', 'local_qlogin_shomokh')
    ), 'forgot-password-link');
}
echo html_writer::div(html_writer::link(
    new moodle_url('/local/qlogin_shomokh/index.php'),
    get_string('backtologin', 'local_qlogin_shomokh')
), 'forgot-password-link');
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
