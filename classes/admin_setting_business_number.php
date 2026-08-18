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

/**
 * Admin setting business number functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Prevents Meta identifiers from being saved as a wa.me destination.
 */
final class admin_setting_business_number extends \admin_setting_configtext {
    /**
     * Validate and store a canonical international phone number.
     */
    public function write_setting($data) {
        $input = trim((string)$data);
        $number = manager::normalise_phone($input);
        if ($input !== '' && $number === '') {
            return get_string('error:businessnumber', 'local_qlogin_shomokh');
        }
        return parent::write_setting($number);
    }
}
