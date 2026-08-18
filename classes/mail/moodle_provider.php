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
 * Moodle provider functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh\mail;

/**
 * Compatibility provider using Moodle's configured outgoing mail.
 */
final class moodle_provider implements provider_interface {
    /**
     * Name method.
     */
    public function name(): string {
        return 'moodle';
    }

    /**
     * Send method.
     */
    public function send(message $message): result {
        global $DB;
        try {
            $user = $message->userid ? $DB->get_record('user', ['id' => $message->userid, 'deleted' => 0]) : false;
            // Email_to_user() sends to the address on the user object. A health-check
            // message may deliberately target another authorised test address, so do
            // not silently send it to the administrator's profile address.
            if (!$user || strcasecmp(trim((string)$user->email), trim($message->to)) !== 0) {
                $user = (object)[
                    'id' => -99,
                    'email' => $message->to,
                    'firstname' => '',
                    'lastname' => '',
                    'maildisplay' => 1,
                    'mailformat' => 1,
                    'deleted' => 0,
                    'suspended' => 0,
                    'auth' => 'manual',
                    'lang' => current_language(),
                ];
            }
            $accepted = email_to_user($user, \core_user::get_support_user(), $message->subject, $message->text);
            return new result(
                (bool)$accepted,
                !$accepted,
                $accepted ? 'accepted' : 'retry',
                0,
                null,
                $accepted ? '' : 'email_to_user_failed'
            );
        } catch (\Throwable $exception) {
            return new result(false, true, 'retry', 0, null, get_class($exception));
        }
    }
}
