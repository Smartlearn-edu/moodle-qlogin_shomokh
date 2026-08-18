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

namespace local_qlogin_shomokh\task;

/**
 * Scheduled task to process verifications, grace periods, and reminders.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class process_verifications extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string Task name.
     */
    public function get_name(): string {
        return get_string('task:processverifications', 'local_qlogin_shomokh');
    }

    /**
     * Execute task logic.
     */
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
                $record = $DB->get_record(
                    'local_qlogin_shomokh_verify',
                    ['userid' => $userid, 'state' => \local_qlogin_shomokh\verification::EXPIRED]
                );
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
            if (
                !\local_qlogin_shomokh\verification::requires_enforcement($userid)
                    || (string)get_config('local_qlogin_shomokh', 'expiredaction') === 'remind'
            ) {
                \local_qlogin_shomokh\enforcement::release($userid);
            }
        }

        $DB->delete_records_select(
            'local_qlogin_shomokh_recov',
            'expiresat < :cutoff',
            ['cutoff' => time() - DAYSECS]
        );
        $DB->delete_records_select(
            'local_qlogin_shomokh_reset',
            'expiresat < :cutoff',
            ['cutoff' => time() - DAYSECS]
        );
        $retentiondays = max(7, min(3650, (int)get_config('local_qlogin_shomokh', 'eventretentiondays')));
        $DB->delete_records_select(
            'local_qlogin_shomokh_event',
            'timecreated < :cutoff',
            ['cutoff' => time() - ($retentiondays * DAYSECS)]
        );
        $mailretention = max(7, min(3650, (int)get_config('local_qlogin_shomokh', 'maillogretentiondays')));
        $DB->delete_records_select(
            'local_qlogin_shomokh_mail',
            'timecreated < :cutoff',
            ['cutoff' => time() - ($mailretention * DAYSECS)]
        );
    }
}
