<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Administrator-only transactional email test form. */
final class test_email_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'recipient', get_string('health:testrecipient', 'local_qlogin_shomokh'), [
            'type' => 'email',
            'autocomplete' => 'email',
            'maxlength' => 254,
        ]);
        $mform->setType('recipient', PARAM_EMAIL);
        $mform->addRule('recipient', null, 'required', null, 'client');
        $mform->addRule('recipient', null, 'email', null, 'client');
        $this->add_action_buttons(false, get_string('health:testsend', 'local_qlogin_shomokh'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (\local_qlogin_shomokh\manager::normalise_email($data['recipient'] ?? '') === '') {
            $errors['recipient'] = get_string('error:email', 'local_qlogin_shomokh');
        }
        return $errors;
    }
}

