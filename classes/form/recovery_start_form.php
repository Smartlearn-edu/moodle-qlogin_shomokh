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

/** Starts a self-initiated WhatsApp recovery. */
final class recovery_start_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'text',
            'phone',
            get_string('phone', 'local_qlogin_shomokh'),
            ['autocomplete' => 'tel', 'inputmode' => 'tel', 'dir' => 'ltr', 'autofocus' => 'autofocus',
            'maxlength' => '32']
        );
        $mform->setType('phone', PARAM_NOTAGS);
        $mform->addRule('phone', null, 'required', null, 'client');
        $mform->addElement('hidden', 'phonecountrycode', '');
        $mform->setType('phonecountrycode', PARAM_ALPHANUM);
        $this->add_action_buttons(false, get_string('recovery:start', 'local_qlogin_shomokh'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (
            \local_qlogin_shomokh\manager::normalise_submitted_phone(
                $data['phone'] ?? '',
                $data['phonecountrycode'] ?? ''
            ) === ''
        ) {
            $errors['phone'] = get_string('error:phone', 'local_qlogin_shomokh');
        }
        return $errors;
    }
}
