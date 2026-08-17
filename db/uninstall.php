<?php
// This file is part of Moodle - https://moodle.org/

/** Safe uninstall cleanup. @package local_qlogin_shomokh */
defined('MOODLE_INTERNAL') || die();

/** Releases only restrictions tracked as having been applied by this plugin. */
function xmldb_local_qlogin_shomokh_uninstall(): bool {
    global $DB;
    $dbman = $DB->get_manager();
    $hasaccountlocks = $dbman->table_exists('local_qlogin_shomokh_lock');
    $hasenrolmentlocks = $dbman->table_exists('local_qlogin_shomokh_enlock');
    if (!$hasaccountlocks && !$hasenrolmentlocks) {
        return true;
    }
    $userids = [];
    if ($hasaccountlocks) {
        foreach ($DB->get_records('local_qlogin_shomokh_lock') as $lock) {
            $userids[(int)$lock->userid] = true;
        }
    }
    if ($hasenrolmentlocks) {
        foreach ($DB->get_records('local_qlogin_shomokh_enlock') as $lock) {
            $userids[(int)$lock->userid] = true;
        }
    }
    foreach (array_keys($userids) as $userid) {
        \local_qlogin_shomokh\enforcement::release($userid);
    }
    return true;
}
