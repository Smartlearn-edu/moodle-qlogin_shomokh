<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Admin form for deterministic repair of one duplicated country-code phone. */
final class repair_phone_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'repairuserid',
            get_string('migration:repairuserid', 'local_qlogin_shomokh'), [
                'inputmode' => 'numeric',
                'maxlength' => '10',
            ]);
        $mform->setType('repairuserid', PARAM_INT);
        $mform->addRule('repairuserid', null, 'required', null, 'client');
        $this->add_action_buttons(false,
            get_string('migration:repairbutton', 'local_qlogin_shomokh'));
    }
}
