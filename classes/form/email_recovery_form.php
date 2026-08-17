<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Email-only password recovery form without the core username/email toggle. */
final class email_recovery_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'recoveryemail', get_string('email', 'local_qlogin_shomokh'), [
            'autocomplete' => 'email',
            'type' => 'email',
            'maxlength' => '254',
        ]);
        $mform->setType('recoveryemail', PARAM_EMAIL);
        $mform->addRule('recoveryemail', null, 'required', null, 'client');
        $mform->addRule('recoveryemail', null, 'email', null, 'client');
        $this->add_action_buttons(false, get_string('recovery:emailsubmit', 'local_qlogin_shomokh'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (\local_qlogin_shomokh\manager::normalise_email($data['recoveryemail'] ?? '') === '') {
            $errors['recoveryemail'] = get_string('error:email', 'local_qlogin_shomokh');
        }
        return $errors;
    }
}
