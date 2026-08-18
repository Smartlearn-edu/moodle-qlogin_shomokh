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

/**
 * Email recovery form form.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class email_recovery_form extends \moodleform {
    /**
     * Form definition.
     */
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

    /**
     * Validate form data.
     *
     * @param array $data Submitted data.
     * @param array $files Uploaded files.
     * @return array Errors array.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (\local_qlogin_shomokh\manager::normalise_email($data['recoveryemail'] ?? '') === '') {
            $errors['recoveryemail'] = get_string('error:email', 'local_qlogin_shomokh');
        }
        return $errors;
    }
}
