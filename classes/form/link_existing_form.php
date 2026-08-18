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

/**
 * Link existing form form.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_existing_form extends \moodleform {
    /**
     * Form definition.
     */
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
            $mform->addElement(
                'text',
                'identifier',
                get_string('claim:identifier', 'local_qlogin_shomokh'),
                [
                    'autocomplete' => 'username',
                    'maxlength' => '254',
                ]
            );
            $mform->setType('identifier', PARAM_RAW_TRIMMED);
            $mform->addRule('identifier', null, 'required', null, 'client');

            $mform->addElement('password', 'password', get_string('password', 'local_qlogin_shomokh'), [
                'autocomplete' => 'current-password',
            ]);
            $mform->setType('password', PARAM_RAW);
            $mform->addRule('password', null, 'required', null, 'client');
        } else if ($changing) {
            $mform->addElement(
                'password',
                'password',
                get_string('claim:currentpassword', 'local_qlogin_shomokh'),
                [
                    'autocomplete' => 'current-password',
                ]
            );
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
        $mform->setType('phonecountrycode', PARAM_INT);

        $this->add_action_buttons(false, get_string(
            $changing ? 'claim:changesubmit' : 'claim:submit',
            'local_qlogin_shomokh'
        ));
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
        $authenticated = !empty($this->_customdata['authenticated']);
        $changing = !empty($this->_customdata['changing']);
        if (!$authenticated && trim((string)($data['identifier'] ?? '')) === '') {
            $errors['identifier'] = get_string('claim:identifierrequired', 'local_qlogin_shomokh');
        }
        if ((!$authenticated || $changing) && (string)($data['password'] ?? '') === '') {
            $errors['password'] = get_string('error:password', 'local_qlogin_shomokh');
        }
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
