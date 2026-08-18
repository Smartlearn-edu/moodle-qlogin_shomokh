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
 * Exemption form form.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exemption_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('select', 'scope', get_string('manage:scope', 'local_qlogin_shomokh'), [
            'user' => get_string('user'), 'cohort' => get_string('cohort', 'cohort'),
        ]);
        $mform->setType('scope', PARAM_ALPHA);
        $mform->addElement('text', 'scopeid', get_string('manage:scopeid', 'local_qlogin_shomokh'));
        $mform->setType('scopeid', PARAM_INT);
        $mform->addRule('scopeid', null, 'required', null, 'client');
        $mform->addElement('select', 'channel', get_string('manage:channel', 'local_qlogin_shomokh'), [
            'all' => get_string('manage:allchannels', 'local_qlogin_shomokh'),
            'email' => get_string('email', 'local_qlogin_shomokh'),
            'phone' => get_string('phone', 'local_qlogin_shomokh'),
        ]);
        $mform->setType('channel', PARAM_ALPHA);
        $mform->addElement('text', 'reason', get_string('manage:reason', 'local_qlogin_shomokh'));
        $mform->setType('reason', PARAM_TEXT);
        $this->add_action_buttons(false, get_string('manage:addexemption', 'local_qlogin_shomokh'));
    }
}
