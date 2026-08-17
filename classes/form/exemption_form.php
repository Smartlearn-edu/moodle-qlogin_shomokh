<?php
// This file is part of Moodle - https://moodle.org/

namespace local_qlogin_shomokh\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Adds a user or cohort exemption. */
final class exemption_form extends \moodleform {
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
