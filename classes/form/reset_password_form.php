<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Sets a new password after WhatsApp verification. */
final class reset_password_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('password', 'password', get_string('newpassword'), [
            'autocomplete' => 'new-password',
        ]);
        $mform->setType('password', PARAM_RAW);
        $mform->addRule('password', null, 'required', null, 'client');
        $mform->addElement('password', 'password2', get_string('recovery:newpasswordagain', 'local_qlogin_shomokh'), [
            'autocomplete' => 'new-password',
        ]);
        $mform->setType('password2', PARAM_RAW);
        $mform->addRule('password2', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('recovery:savepassword', 'local_qlogin_shomokh'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((string)($data['password'] ?? '') === '') {
            $errors['password'] = get_string('error:password', 'local_qlogin_shomokh');
        } else if (($data['password'] ?? '') !== ($data['password2'] ?? '')) {
            $errors['password2'] = get_string('passwordsdiffer');
        }
        return $errors;
    }
}
