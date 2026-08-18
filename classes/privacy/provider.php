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
 * Provider functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for local_qlogin_shomokh.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Get metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_qlogin_shomokh_verify',
            [
                'userid' => 'privacy:metadata:verify', 'channel' => 'privacy:metadata:verify',
                'target' => 'privacy:metadata:verify', 'state' => 'privacy:metadata:verify',
                'tokenhash' => 'privacy:metadata:verify', 'expiresat' => 'privacy:metadata:verify',
                'verifiedat' => 'privacy:metadata:verify', 'lastsentat' => 'privacy:metadata:verify',
                'remindercount' => 'privacy:metadata:verify', 'lastremindedat' => 'privacy:metadata:verify',
                'timecreated' => 'privacy:metadata:verify', 'timemodified' => 'privacy:metadata:verify',
            ],
            'privacy:metadata:verify'
        );
        $collection->add_database_table(
            'local_qlogin_shomokh_event',
            [
                'userid' => 'privacy:metadata:event', 'messageid' => 'privacy:metadata:event',
                'eventtype' => 'privacy:metadata:event', 'status' => 'privacy:metadata:event',
                'timecreated' => 'privacy:metadata:event',
            ],
            'privacy:metadata:event'
        );
        $collection->add_database_table(
            'local_qlogin_shomokh_recov',
            [
                'userid' => 'privacy:metadata:recovery', 'phone' => 'privacy:metadata:recovery',
                'tokenhash' => 'privacy:metadata:recovery', 'state' => 'privacy:metadata:recovery',
                'expiresat' => 'privacy:metadata:recovery', 'verifiedat' => 'privacy:metadata:recovery',
                'attempts' => 'privacy:metadata:recovery', 'timecreated' => 'privacy:metadata:recovery',
                'timemodified' => 'privacy:metadata:recovery',
            ],
            'privacy:metadata:recovery'
        );
        $collection->add_database_table(
            'local_qlogin_shomokh_lock',
            [
                'userid' => 'privacy:metadata:restrictions', 'previoussuspended' => 'privacy:metadata:restrictions',
                'reason' => 'privacy:metadata:restrictions', 'timecreated' => 'privacy:metadata:restrictions',
            ],
            'privacy:metadata:restrictions'
        );
        $collection->add_database_table(
            'local_qlogin_shomokh_enlock',
            [
                'userid' => 'privacy:metadata:restrictions', 'userenrolid' => 'privacy:metadata:restrictions',
                'previousstatus' => 'privacy:metadata:restrictions',
                'timecreated' => 'privacy:metadata:restrictions',
            ],
            'privacy:metadata:restrictions'
        );
        $collection->add_database_table(
            'local_qlogin_shomokh_exempt',
            [
                'scope' => 'privacy:metadata:exemptions', 'scopeid' => 'privacy:metadata:exemptions',
                'channel' => 'privacy:metadata:exemptions', 'reason' => 'privacy:metadata:exemptions',
                'createdby' => 'privacy:metadata:exemptions',
                'timecreated' => 'privacy:metadata:exemptions',
            ],
            'privacy:metadata:exemptions'
        );
        $collection->add_database_table(
            'local_qlogin_shomokh_mail',
            [
                'userid' => 'privacy:metadata:mail', 'purpose' => 'privacy:metadata:mail',
                'provider' => 'privacy:metadata:mail', 'recipienthash' => 'privacy:metadata:mail',
                'recipienthint' => 'privacy:metadata:mail', 'messageid' => 'privacy:metadata:mail',
                'idempotencykey' => 'privacy:metadata:mail', 'status' => 'privacy:metadata:mail',
                'httpstatus' => 'privacy:metadata:mail', 'attempts' => 'privacy:metadata:mail',
                'lasterror' => 'privacy:metadata:mail', 'timecreated' => 'privacy:metadata:mail',
                'timemodified' => 'privacy:metadata:mail',
            ],
            'privacy:metadata:mail'
        );
        $collection->add_database_table(
            'local_qlogin_shomokh_reset',
            [
                'userid' => 'privacy:metadata:emailreset', 'channel' => 'privacy:metadata:emailreset',
                'targethash' => 'privacy:metadata:emailreset', 'tokenhash' => 'privacy:metadata:emailreset',
                'state' => 'privacy:metadata:emailreset', 'expiresat' => 'privacy:metadata:emailreset',
                'attempts' => 'privacy:metadata:emailreset', 'timecreated' => 'privacy:metadata:emailreset',
                'timemodified' => 'privacy:metadata:emailreset',
            ],
            'privacy:metadata:emailreset'
        );
        $collection->add_database_table(
            'local_qlogin_shomokh_alias',
            [
                'userid' => 'privacy:metadata:alias', 'phone' => 'privacy:metadata:alias',
                'source' => 'privacy:metadata:alias', 'status' => 'privacy:metadata:alias',
                'verified' => 'privacy:metadata:alias', 'createdby' => 'privacy:metadata:alias',
                'timecreated' => 'privacy:metadata:alias', 'timemodified' => 'privacy:metadata:alias',
            ],
            'privacy:metadata:alias'
        );
        $collection->add_external_location_link('whatsapp', [
            'phone' => 'privacy:metadata:whatsapp',
            'message' => 'privacy:metadata:whatsapp',
        ], 'privacy:metadata:whatsapp');
        $collection->add_external_location_link('resend', [
            'email' => 'privacy:metadata:resend',
            'subject' => 'privacy:metadata:resend',
            'content' => 'privacy:metadata:resend',
        ], 'privacy:metadata:resend');
        return $collection;
    }

    /**
     * Get contexts for userid method.
     */
    public static function get_contexts_for_userid($userid): contextlist {
        global $DB;
        $contexts = new contextlist();
        foreach (
            ['local_qlogin_shomokh_verify', 'local_qlogin_shomokh_event',
            'local_qlogin_shomokh_recov', 'local_qlogin_shomokh_lock', 'local_qlogin_shomokh_enlock',
            'local_qlogin_shomokh_mail', 'local_qlogin_shomokh_reset', 'local_qlogin_shomokh_alias'] as $table
        ) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                $contexts->add_system_context();
                break;
            }
        }
        if (
            $DB->record_exists('local_qlogin_shomokh_exempt', ['scope' => 'user', 'scopeid' => $userid])
                || $DB->record_exists('local_qlogin_shomokh_exempt', ['createdby' => $userid])
                || $DB->record_exists('local_qlogin_shomokh_alias', ['createdby' => $userid])
        ) {
            $contexts->add_system_context();
        }
        return $contexts;
    }

    /**
     * Export user data method.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $context = \context_system::instance();
        if (!in_array($context->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $verification = $DB->get_records(
            'local_qlogin_shomokh_verify',
            ['userid' => $userid],
            '',
            'id, channel, target, state, expiresat, verifiedat, timecreated, timemodified'
        );
        $recovery = $DB->get_records(
            'local_qlogin_shomokh_recov',
            ['userid' => $userid],
            '',
            'id, phone, state, expiresat, verifiedat, attempts, timecreated, timemodified'
        );
        $events = $DB->get_records(
            'local_qlogin_shomokh_event',
            ['userid' => $userid],
            '',
            'id, messageid, eventtype, status, timecreated'
        );
        $exemptions = $DB->get_records_select(
            'local_qlogin_shomokh_exempt',
            '(scope = :scope AND scopeid = :scopeid) OR createdby = :createdby',
            ['scope' => 'user', 'scopeid' => $userid, 'createdby' => $userid],
            '',
            'id, scope, scopeid, channel, reason, createdby, timecreated'
        );
        $accountlocks = $DB->get_records(
            'local_qlogin_shomokh_lock',
            ['userid' => $userid],
            '',
            'id, previoussuspended, reason, timecreated'
        );
        $enrolmentlocks = $DB->get_records(
            'local_qlogin_shomokh_enlock',
            ['userid' => $userid],
            '',
            'id, userenrolid, previousstatus, timecreated'
        );
        $mail = $DB->get_records(
            'local_qlogin_shomokh_mail',
            ['userid' => $userid],
            '',
            'id, purpose, provider, recipienthint, status, httpstatus, attempts, timecreated, timemodified'
        );
        $emailreset = $DB->get_records(
            'local_qlogin_shomokh_reset',
            ['userid' => $userid],
            '',
            'id, channel, state, expiresat, attempts, timecreated, timemodified'
        );
        $aliases = $DB->get_records_select(
            'local_qlogin_shomokh_alias',
            'userid = :userid OR createdby = :createdby',
            ['userid' => $userid, 'createdby' => $userid],
            '',
            'id, userid, phone, source, status, verified, createdby, timecreated, timemodified'
        );
        $data = (object)['verification' => array_values($verification),
            'recovery' => array_values($recovery), 'events' => array_values($events),
            'exemptions' => array_values($exemptions),
            'mail' => array_values($mail),
            'emailreset' => array_values($emailreset),
            'aliases' => array_values($aliases),
            'restrictions' => (object)[
                'account' => array_values($accountlocks),
                'enrolments' => array_values($enrolmentlocks),
            ]];
        writer::with_context($context)->export_data([get_string('pluginname', 'local_qlogin_shomokh')], $data);
    }

    /**
     * Delete data for all users in context method.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = [];
        foreach (['local_qlogin_shomokh_lock', 'local_qlogin_shomokh_enlock'] as $table) {
            foreach ($DB->get_records($table, [], '', 'id, userid') as $record) {
                $userids[(int)$record->userid] = true;
            }
        }
        foreach (array_keys($userids) as $userid) {
            \local_qlogin_shomokh\enforcement::release($userid);
        }
        foreach (
            ['local_qlogin_shomokh_event', 'local_qlogin_shomokh_recov',
            'local_qlogin_shomokh_enlock', 'local_qlogin_shomokh_lock',
            'local_qlogin_shomokh_exempt', 'local_qlogin_shomokh_verify', 'local_qlogin_shomokh_mail',
            'local_qlogin_shomokh_reset', 'local_qlogin_shomokh_alias'] as $table
        ) {
            $DB->delete_records($table);
        }
    }

    /**
     * Delete data for user method.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $context = \context_system::instance();
        if (!in_array($context->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        \local_qlogin_shomokh\enforcement::release($userid);
        foreach (
            ['local_qlogin_shomokh_event', 'local_qlogin_shomokh_recov',
            'local_qlogin_shomokh_verify', 'local_qlogin_shomokh_mail',
            'local_qlogin_shomokh_reset', 'local_qlogin_shomokh_alias'] as $table
        ) {
            $DB->delete_records($table, ['userid' => $userid]);
        }
        $DB->delete_records('local_qlogin_shomokh_exempt', ['scope' => 'user', 'scopeid' => $userid]);
        $DB->delete_records('local_qlogin_shomokh_exempt', ['createdby' => $userid]);
        $DB->set_field('local_qlogin_shomokh_alias', 'createdby', null, ['createdby' => $userid]);
    }
}
