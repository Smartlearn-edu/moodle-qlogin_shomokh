<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Moodle form for phone-first login and registration. */
final class auth_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $action = ($this->_customdata['action'] ?? 'login') === 'register' ? 'register' : 'login';
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('text', 'website', get_string('website', 'local_qlogin_shomokh'),
            ['tabindex' => '-1', 'autocomplete' => 'off']);
        $mform->setType('website', PARAM_NOTAGS);
        $mform->addElement('text', 'identifier', get_string('loginidentifier', 'local_qlogin_shomokh'), [
            'autocomplete' => 'username',
            'autofocus' => $action === 'login' ? 'autofocus' : null,
            'maxlength' => '254',
        ]);
        $mform->setType('identifier', PARAM_RAW_TRIMMED);
        $mform->addElement('text', 'phone', get_string('phone', 'local_qlogin_shomokh'), [
            'autocomplete' => 'tel',
            'inputmode' => 'tel', 'dir' => 'ltr', 'autofocus' => $action === 'register' ? 'autofocus' : null,
            'maxlength' => '32',
        ]);
        $mform->setType('phone', PARAM_NOTAGS);
        $mform->addHelpButton('phone', 'phone', 'local_qlogin_shomokh');
        $mform->addElement('hidden', 'phonecountrycode', '');
        $mform->setType('phonecountrycode', PARAM_INT);
        $mform->addElement('text', 'fullname', get_string('fullname', 'local_qlogin_shomokh'),
            ['autocomplete' => 'name', 'maxlength' => '201']);
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addElement('text', 'email', get_string('email', 'local_qlogin_shomokh'),
            ['autocomplete' => 'email', 'type' => 'email', 'maxlength' => '254']);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'email', null, 'client');
        $mform->addElement('password', 'password', get_string('password', 'local_qlogin_shomokh'),
            ['autocomplete' => $action === 'register' ? 'new-password' : 'current-password']);
        $mform->setType('password', PARAM_RAW);
        $mform->addRule('password', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string(
            $action === 'register' ? 'submit_register' : 'submit_login',
            'local_qlogin_shomokh'
        ));
    }

    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        $action = $data['action'] ?? '';
        $phone = \local_qlogin_shomokh\manager::normalise_submitted_phone(
            $data['phone'] ?? '',
            $data['phonecountrycode'] ?? ''
        );
        if (!in_array($action, ['login', 'register'], true)) {
            $errors['identifier'] = get_string('invalidaction', 'local_qlogin_shomokh');
        } else if ($action === 'login' && trim((string)($data['identifier'] ?? '')) === '') {
            $errors['identifier'] = get_string('error:identifier', 'local_qlogin_shomokh');
        } else if ($action === 'register' && $phone === '') {
            $errors['phone'] = get_string('error:phone', 'local_qlogin_shomokh');
        }
        if ((string)($data['password'] ?? '') === '') {
            $errors['password'] = get_string('error:password', 'local_qlogin_shomokh');
        }
        if ($action === 'register') {
            $fullname = trim($data['fullname'] ?? '');
            if ($fullname === '') {
                $errors['fullname'] = get_string('error:fullname', 'local_qlogin_shomokh');
            } else {
                $nameparts = preg_split('/\s+/u', $fullname, 2);
                if (\core_text::strlen($nameparts[0]) > 100
                        || \core_text::strlen($nameparts[1] ?? '-') > 100) {
                    $errors['fullname'] = get_string('error:fullnamelong', 'local_qlogin_shomokh');
                }
            }
            $email = \local_qlogin_shomokh\manager::normalise_email($data['email'] ?? '');
            if ($email === '') {
                $errors['email'] = get_string('error:email', 'local_qlogin_shomokh');
            } else {
                $emailsql = $DB->sql_equal('email', ':email', false);
                if ($DB->record_exists_select('user', "$emailsql AND deleted = :deleted",
                        ['email' => $email, 'deleted' => 0])) {
                    $key = \local_qlogin_shomokh\migration::self_claim_available()
                        ? 'error:emailexistsclaim' : 'error:emailexists';
                    $errors['email'] = get_string($key, 'local_qlogin_shomokh');
                }
            }
            if ($phone !== '' && \local_qlogin_shomokh\migration::phone_in_use($phone)) {
                $errors['phone'] = get_string('error:userexists', 'local_qlogin_shomokh');
            }
        }
        return $errors;
    }
}
