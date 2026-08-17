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

namespace local_qlogin_shomokh\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Sets a new password after a valid email recovery link. */
final class email_reset_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $token = (string)($this->_customdata['token'] ?? '');
        $mform->addElement('hidden', 'token', $token);
        $mform->setType('token', PARAM_ALPHANUM);
        $mform->addElement('password', 'password', get_string('newpassword'), ['autocomplete' => 'new-password']);
        $mform->setType('password', PARAM_RAW);
        $mform->addRule('password', null, 'required', null, 'client');
        $mform->addElement(
            'password',
            'password2',
            get_string('recovery:newpasswordagain', 'local_qlogin_shomokh'),
            ['autocomplete' => 'new-password']
        );
        $mform->setType('password2', PARAM_RAW);
        $mform->addRule('password2', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('recovery:savepassword', 'local_qlogin_shomokh'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!preg_match('/^[a-f0-9]{64}$/i', (string)($data['token'] ?? ''))) {
            $errors['password'] = get_string('recovery:invalidlink', 'local_qlogin_shomokh');
        } else if ((string)($data['password'] ?? '') === '') {
            $errors['password'] = get_string('error:password', 'local_qlogin_shomokh');
        } else if (($data['password'] ?? '') !== ($data['password2'] ?? '')) {
            $errors['password2'] = get_string('passwordsdiffer');
        }
        return $errors;
    }
}
