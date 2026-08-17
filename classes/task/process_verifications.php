<?php
// This file is part of Moodle - https://moodle.org/

/** Daily verification processing. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\task;

defined('MOODLE_INTERNAL') || die();

final class process_verifications extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task:processverifications', 'local_qlogin_shomokh');
    }

    public function execute(): void {
        global $DB;
        if (!\local_qlogin_shomokh\verification::available()) {
            $dbman = $DB->get_manager();
            $userids = [];
            if ($dbman->table_exists('local_qlogin_shomokh_lock')) {
                foreach ($DB->get_records('local_qlogin_shomokh_lock') as $lock) {
                    $userids[(int)$lock->userid] = true;
                }
            }
            if ($dbman->table_exists('local_qlogin_shomokh_enlock')) {
                foreach ($DB->get_records('local_qlogin_shomokh_enlock') as $lock) {
                    $userids[(int)$lock->userid] = true;
                }
            }
            foreach (array_keys($userids) as $userid) {
                \local_qlogin_shomokh\enforcement::release($userid);
            }
            return;
        }
        \local_qlogin_shomokh\verification::expire_due();
        $sql = "SELECT DISTINCT userid FROM {local_qlogin_shomokh_verify} WHERE state = :state";
        foreach ($DB->get_records_sql($sql, ['state' => \local_qlogin_shomokh\verification::EXPIRED]) as $row) {
            $userid = (int)$row->userid;
            if (\local_qlogin_shomokh\verification::requires_enforcement($userid)) {
                \local_qlogin_shomokh\enforcement::reconcile($userid);
                $record = $DB->get_record('local_qlogin_shomokh_verify',
                    ['userid' => $userid, 'state' => \local_qlogin_shomokh\verification::EXPIRED]);
                if ($record) {
                    \local_qlogin_shomokh\verification::remind($record);
                }
            } else {
                \local_qlogin_shomokh\enforcement::release($userid);
            }
        }

        // Release stale restrictions after a policy, grace-period or eligibility change.
        $restrictedusers = [];
        foreach ($DB->get_records('local_qlogin_shomokh_lock') as $lock) {
            $restrictedusers[(int)$lock->userid] = true;
        }
        foreach ($DB->get_records('local_qlogin_shomokh_enlock') as $lock) {
            $restrictedusers[(int)$lock->userid] = true;
        }
        foreach (array_keys($restrictedusers) as $userid) {
            if (!\local_qlogin_shomokh\verification::requires_enforcement($userid)
                    || (string)get_config('local_qlogin_shomokh', 'expiredaction') === 'remind') {
                \local_qlogin_shomokh\enforcement::release($userid);
            }
        }

        $DB->delete_records_select('local_qlogin_shomokh_recov',
            'expiresat < :cutoff', ['cutoff' => time() - DAYSECS]);
        $DB->delete_records_select('local_qlogin_shomokh_reset',
            'expiresat < :cutoff', ['cutoff' => time() - DAYSECS]);
        $retentiondays = max(7, min(3650, (int)get_config('local_qlogin_shomokh', 'eventretentiondays')));
        $DB->delete_records_select('local_qlogin_shomokh_event',
            'timecreated < :cutoff', ['cutoff' => time() - ($retentiondays * DAYSECS)]);
        $mailretention = max(7, min(3650, (int)get_config('local_qlogin_shomokh', 'maillogretentiondays')));
        $DB->delete_records_select('local_qlogin_shomokh_mail',
            'timecreated < :cutoff', ['cutoff' => time() - ($mailretention * DAYSECS)]);
    }
}
