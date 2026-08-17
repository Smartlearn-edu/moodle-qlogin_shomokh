<?php
// This file is part of Moodle - https://moodle.org/

/** Moodle mail provider. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\mail;

defined('MOODLE_INTERNAL') || die();

/** Compatibility provider using Moodle's configured outgoing mail. */
final class moodle_provider implements provider_interface {
    public function name(): string {
        return 'moodle';
    }

    public function send(message $message): result {
        global $DB;
        try {
            $user = $message->userid ? $DB->get_record('user', ['id' => $message->userid, 'deleted' => 0]) : false;
            // email_to_user() sends to the address on the user object. A health-check
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
            return new result((bool)$accepted, !$accepted, $accepted ? 'accepted' : 'retry', 0, null,
                $accepted ? '' : 'email_to_user_failed');
        } catch (\Throwable $exception) {
            return new result(false, true, 'retry', 0, null, get_class($exception));
        }
    }
}
