<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Form for attaching a mobile sign-in alias to an existing Moodle account. */
final class link_existing_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $authenticated = !empty($this->_customdata['authenticated']);
        $changing = !empty($this->_customdata['changing']);

        $mform->addElement('text', 'website', get_string('website', 'local_qlogin_shomokh'), [
            'tabindex' => '-1',
            'autocomplete' => 'off',
        ]);
        $mform->setType('website', PARAM_NOTAGS);

        if (!$authenticated) {
            $mform->addElement('text', 'identifier',
                get_string('claim:identifier', 'local_qlogin_shomokh'), [
                    'autocomplete' => 'username',
                    'maxlength' => '254',
                ]);
            $mform->setType('identifier', PARAM_RAW_TRIMMED);
            $mform->addRule('identifier', null, 'required', null, 'client');

            $mform->addElement('password', 'password', get_string('password', 'local_qlogin_shomokh'), [
                'autocomplete' => 'current-password',
            ]);
            $mform->setType('password', PARAM_RAW);
            $mform->addRule('password', null, 'required', null, 'client');
        } else if ($changing) {
            $mform->addElement('password', 'password',
                get_string('claim:currentpassword', 'local_qlogin_shomokh'), [
                    'autocomplete' => 'current-password',
                ]);
            $mform->setType('password', PARAM_RAW);
            $mform->addRule('password', null, 'required', null, 'client');
        }

        $mform->addElement('text', 'phone', get_string('claim:phone', 'local_qlogin_shomokh'), [
            'autocomplete' => 'tel',
            'inputmode' => 'tel',
            'dir' => 'ltr',
            'maxlength' => '32',
            'autofocus' => $authenticated ? 'autofocus' : null,
        ]);
        $mform->setType('phone', PARAM_NOTAGS);
        $mform->addRule('phone', null, 'required', null, 'client');
        $mform->addHelpButton('phone', 'phone', 'local_qlogin_shomokh');
        $mform->addElement('hidden', 'phonecountrycode', '');
        $mform->setType('phonecountrycode', PARAM_ALPHANUM);

        $this->add_action_buttons(false, get_string($changing ? 'claim:changesubmit' : 'claim:submit',
            'local_qlogin_shomokh'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $authenticated = !empty($this->_customdata['authenticated']);
        $changing = !empty($this->_customdata['changing']);
        if (!$authenticated && trim((string)($data['identifier'] ?? '')) === '') {
            $errors['identifier'] = get_string('claim:identifierrequired', 'local_qlogin_shomokh');
        }
        if ((!$authenticated || $changing) && (string)($data['password'] ?? '') === '') {
            $errors['password'] = get_string('error:password', 'local_qlogin_shomokh');
        }
        if (\local_qlogin_shomokh\manager::normalise_submitted_phone(
                $data['phone'] ?? '', $data['phonecountrycode'] ?? '') === '') {
            $errors['phone'] = get_string('error:phone', 'local_qlogin_shomokh');
        }
        return $errors;
    }
}
