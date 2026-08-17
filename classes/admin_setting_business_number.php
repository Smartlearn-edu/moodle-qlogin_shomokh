<?php
// This file is part of Moodle - https://moodle.org/

/** Validated WhatsApp display-number setting. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Prevents Meta identifiers from being saved as a wa.me destination. */
final class admin_setting_business_number extends \admin_setting_configtext {
    /** Validate and store a canonical international phone number. */
    public function write_setting($data) {
        $input = trim((string)$data);
        $number = manager::normalise_phone($input);
        if ($input !== '' && $number === '') {
            return get_string('error:businessnumber', 'local_qlogin_shomokh');
        }
        return parent::write_setting($number);
    }
}
