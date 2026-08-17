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

/** Email correction and verification form. */
final class email_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'text',
            'email',
            get_string('email', 'local_qlogin_shomokh'),
            ['autocomplete' => 'email', 'type' => 'email', 'maxlength' => '254']
        );
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required', null, 'client');
        $mform->addRule('email', null, 'email', null, 'client');
        $this->add_action_buttons(false, get_string('emailpage:saveandsend', 'local_qlogin_shomokh'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (\local_qlogin_shomokh\manager::normalise_email($data['email'] ?? '') === '') {
            $errors['email'] = get_string('error:email', 'local_qlogin_shomokh');
        }
        return $errors;
    }
}
