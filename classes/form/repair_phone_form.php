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

/** Admin form for deterministic repair of one duplicated country-code phone. */
final class repair_phone_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'text',
            'repairuserid',
            get_string('migration:repairuserid', 'local_qlogin_shomokh'),
            [
                'inputmode' => 'numeric',
                'maxlength' => '10',
            ]
        );
        $mform->setType('repairuserid', PARAM_INT);
        $mform->addRule('repairuserid', null, 'required', null, 'client');
        $this->add_action_buttons(
            false,
            get_string('migration:repairbutton', 'local_qlogin_shomokh')
        );
    }
}
