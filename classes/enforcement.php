<?php
// This file is part of Moodle - https://moodle.org/

/** Reversible expiry enforcement. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Applies only restrictions which can later be safely identified and reversed. */
final class enforcement {
    /** Reconciles one user with the configured action. */
    public static function reconcile(int $userid): void {
        $action = (string)get_config('local_qlogin_shomokh', 'expiredaction');
        if (!verification::requires_enforcement($userid) || $action === 'remind' || $action === '') {
            self::release($userid);
            return;
        }
        if ($action === 'courses') {
            self::release_account($userid);
            self::suspend_courses($userid);
        } else if ($action === 'suspend') {
            self::release_courses($userid);
            self::suspend_account($userid);
        }
    }

    /** Releases restrictions as soon as no required channel is overdue. */
    public static function release_if_complete(int $userid): void {
        if (!verification::requires_enforcement($userid)) {
            self::release($userid);
        }
    }

    /** Releases every restriction previously applied by this plugin. */
    public static function release(int $userid): void {
        self::release_account($userid);
        self::release_courses($userid);
    }

    /** Suspends an account only when it was active. */
    private static function suspend_account(int $userid): void {
        global $CFG, $DB;
        if ($DB->record_exists('local_qlogin_shomokh_lock', ['userid' => $userid])) {
            return;
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user || !empty($user->suspended) || is_siteadmin($userid)) {
            return;
        }
        require_once($CFG->dirroot . '/user/lib.php');
        $DB->insert_record('local_qlogin_shomokh_lock', (object)[
            'userid' => $userid, 'previoussuspended' => 0, 'reason' => 'verificationexpired', 'timecreated' => time(),
        ]);
        try {
            $user->suspended = 1;
            user_update_user($user, false, true);
        } catch (\Throwable $exception) {
            $DB->delete_records('local_qlogin_shomokh_lock', ['userid' => $userid]);
            throw $exception;
        }
    }

    /** Restores only an account suspended by this plugin. */
    private static function release_account(int $userid): void {
        global $CFG, $DB;
        if (!$DB->get_manager()->table_exists('local_qlogin_shomokh_lock')) {
            return;
        }
        $lock = $DB->get_record('local_qlogin_shomokh_lock', ['userid' => $userid]);
        if (!$lock) {
            return;
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if ($user && !empty($user->suspended)) {
            require_once($CFG->dirroot . '/user/lib.php');
            $user->suspended = (int)$lock->previoussuspended;
            user_update_user($user, false, true);
        }
        $DB->delete_records('local_qlogin_shomokh_lock', ['id' => $lock->id]);
    }

    /** Suspends each active enrolment and records exactly what changed. */
    private static function suspend_courses(int $userid): void {
        global $DB;
        $sql = "SELECT ue.*, e.enrol, e.id AS enrolinstanceid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND ue.status = :active";
        foreach ($DB->get_records_sql($sql, ['userid' => $userid, 'active' => ENROL_USER_ACTIVE]) as $enrolment) {
            if ($DB->record_exists('local_qlogin_shomokh_enlock', ['userid' => $userid, 'userenrolid' => $enrolment->id])) {
                continue;
            }
            $plugin = enrol_get_plugin($enrolment->enrol);
            $instance = $DB->get_record('enrol', ['id' => $enrolment->enrolinstanceid]);
            if ($plugin && $instance) {
                $DB->insert_record('local_qlogin_shomokh_enlock', (object)[
                    'userid' => $userid, 'userenrolid' => $enrolment->id,
                    'previousstatus' => $enrolment->status, 'timecreated' => time(),
                ]);
                try {
                    $plugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
                } catch (\Throwable $exception) {
                    $DB->delete_records('local_qlogin_shomokh_enlock',
                        ['userid' => $userid, 'userenrolid' => $enrolment->id]);
                    throw $exception;
                }
            }
        }
    }

    /** Restores only enrolments suspended by this plugin. */
    private static function release_courses(int $userid): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_qlogin_shomokh_enlock')) {
            return;
        }
        foreach ($DB->get_records('local_qlogin_shomokh_enlock', ['userid' => $userid]) as $lock) {
            $sql = "SELECT ue.*, e.enrol, e.id AS enrolinstanceid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.id = :id";
            $enrolment = $DB->get_record_sql($sql, ['id' => $lock->userenrolid]);
            if ($enrolment && (int)$enrolment->status === ENROL_USER_SUSPENDED) {
                $plugin = enrol_get_plugin($enrolment->enrol);
                $instance = $DB->get_record('enrol', ['id' => $enrolment->enrolinstanceid]);
                if ($plugin && $instance) {
                    $plugin->update_user_enrol($instance, $userid, (int)$lock->previousstatus);
                }
            }
            $DB->delete_records('local_qlogin_shomokh_enlock', ['id' => $lock->id]);
        }
    }
}
