<?php
// This file is part of Moodle - https://moodle.org/

/** User event observer. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

final class observer {
    /** Starts the grace period when a legacy account first signs in by any route. */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $event->objectid, 'deleted' => 0]);
        if ($user) {
            verification::bootstrap_existing_user($user);
        }
    }

    /** Synchronises required channels after account creation or profile edits. */
    public static function user_changed(\core\event\base $event): void {
        global $DB;
        $user = $event->get_record_snapshot('user', $event->objectid)
            ?: $DB->get_record('user', ['id' => $event->objectid]);
        if ($user) {
            if ($event instanceof \core\event\user_created) {
                verification::track_new_user($user);
            } else {
                verification::ensure_for_user($user);
            }
        }
    }
}
