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
 * Upgrade functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade local_qlogin_shomokh.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_local_qlogin_shomokh_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2026081500) {
        $table = new xmldb_table('local_qlogin_shomokh_verify');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('channel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('target', XMLDB_TYPE_CHAR, '254', null, XMLDB_NOTNULL);
        $table->add_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('state', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('verifiedat', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('lastsentat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('remindercount', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastremindedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('userchannel_uix', XMLDB_INDEX_UNIQUE, ['userid', 'channel']);
        $table->add_index('channeltarget_ix', XMLDB_INDEX_NOTUNIQUE, ['channel', 'target']);
        $table->add_index('tokenhash_ix', XMLDB_INDEX_NOTUNIQUE, ['tokenhash']);
        $table->add_index('stateexpires_ix', XMLDB_INDEX_NOTUNIQUE, ['state', 'expiresat']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_qlogin_shomokh_event');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('messageid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('eventtype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('messageid_uix', XMLDB_INDEX_UNIQUE, ['messageid']);
        $table->add_index('timecreated_ix', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_qlogin_shomokh_exempt');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('scope', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('scopeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('channel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'all');
        $table->add_field('reason', XMLDB_TYPE_TEXT);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('createdby_fk', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
        $table->add_index('scoperecord_uix', XMLDB_INDEX_UNIQUE, ['scope', 'scopeid', 'channel']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_qlogin_shomokh_lock');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('previoussuspended', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_qlogin_shomokh_enlock');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userenrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('previousstatus', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('userenrolid_fk', XMLDB_KEY_FOREIGN, ['userenrolid'], 'user_enrolments', ['id']);
        $table->add_index('userenrol_uix', XMLDB_INDEX_UNIQUE, ['userid', 'userenrolid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_qlogin_shomokh_recov');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('phone', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('state', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('verifiedat', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('phonetoken_ix', XMLDB_INDEX_NOTUNIQUE, ['phone', 'tokenhash']);
        $table->add_index('stateexpires_ix', XMLDB_INDEX_NOTUNIQUE, ['state', 'expiresat']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Preserve email states created by 0.1.x, then retire its one-channel table.
        $legacyemail = new xmldb_table('local_qlogin_shomokh_email');
        if ($dbman->table_exists($legacyemail)) {
            foreach ($DB->get_records('local_qlogin_shomokh_email') as $record) {
                if (!$DB->record_exists('local_qlogin_shomokh_verify', ['userid' => $record->userid, 'channel' => 'email'])) {
                    $DB->insert_record('local_qlogin_shomokh_verify', (object)[
                        'userid' => $record->userid,
                        'channel' => 'email',
                        'target' => $record->email,
                        'tokenhash' => $record->tokenhash,
                        'state' => $record->state,
                        'expiresat' => max((int)$record->expiresat, (int)$record->timecreated + (30 * DAYSECS)),
                        'verifiedat' => $record->verifiedat,
                        'lastsentat' => $record->lastsentat,
                        'remindercount' => 0,
                        'lastremindedat' => 0,
                        'timecreated' => $record->timecreated,
                        'timemodified' => $record->timemodified,
                    ]);
                }
            }
            $dbman->drop_table($legacyemail);
        }

        // Import the separate phone plugin when present, without removing its data.
        $oldphone = new xmldb_table('local_phoneverify');
        if ($dbman->table_exists($oldphone)) {
            foreach ($DB->get_records('local_phoneverify') as $record) {
                if (!$DB->record_exists('local_qlogin_shomokh_verify', ['userid' => $record->userid, 'channel' => 'phone'])) {
                    $DB->insert_record('local_qlogin_shomokh_verify', (object)[
                        'userid' => $record->userid,
                        'channel' => 'phone',
                        'target' => $record->phone,
                        // Verified state is preserved. Pending users receive a fresh unified-format code.
                        'tokenhash' => '',
                        'state' => $record->state,
                        'expiresat' => max((int)$record->expiresat, (int)$record->timecreated + (30 * DAYSECS)),
                        'verifiedat' => $record->verifiedat,
                        'lastsentat' => $record->lastpromptat,
                        'remindercount' => 0,
                        'lastremindedat' => 0,
                        'timecreated' => $record->timecreated,
                        'timemodified' => $record->timemodified,
                    ]);
                }
            }
        }

        // New settings intentionally use a new key; existing 7-day test installs become 30 days.
        if (get_config('local_qlogin_shomokh', 'graceperioddays') === false) {
            set_config('graceperioddays', 30, 'local_qlogin_shomokh');
        }
        foreach (
            [
            'enabled' => 1, 'requireemail' => 1, 'requirephone' => 1, 'expiredaction' => 'remind',
            'reminderintervaldays' => 7, 'maxreminders' => 3, 'resendcooldown' => 600,
            'authtypes' => 'manual,email', 'recoveryenabled' => 1, 'recoveryexpiryminutes' => 15,
            'eventretentiondays' => 90,
            ] as $name => $value
        ) {
            if (get_config('local_qlogin_shomokh', $name) === false) {
                set_config($name, $value, 'local_qlogin_shomokh');
            }
        }
        unset_config('emailgraceperioddays', 'local_qlogin_shomokh');

        upgrade_plugin_savepoint(true, 2026081500, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081501) {
        upgrade_plugin_savepoint(true, 2026081501, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081600) {
        upgrade_plugin_savepoint(true, 2026081600, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081700) {
        upgrade_plugin_savepoint(true, 2026081700, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081701) {
        upgrade_plugin_savepoint(true, 2026081701, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081702) {
        upgrade_plugin_savepoint(true, 2026081702, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081703) {
        // Moodle 4.1 and 4.2 limit XMLDB table names to 28 characters.
        $oldtable = new xmldb_table('local_qlogin_shomokh_recovery');
        $newtable = new xmldb_table('local_qlogin_shomokh_recov');
        if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
            $dbman->rename_table($oldtable, 'local_qlogin_shomokh_recov');
        } else if ($dbman->table_exists($oldtable) && $dbman->table_exists($newtable)) {
            // Recover safely from an earlier partially completed upgrade.
            foreach ($DB->get_records('local_qlogin_shomokh_recovery') as $record) {
                $exists = $DB->record_exists('local_qlogin_shomokh_recov', [
                    'userid' => $record->userid,
                    'tokenhash' => $record->tokenhash,
                    'timecreated' => $record->timecreated,
                ]);
                if (!$exists) {
                    unset($record->id);
                    $DB->insert_record('local_qlogin_shomokh_recov', $record);
                }
            }
            $dbman->drop_table($oldtable);
        }
        if (get_config('local_qlogin_shomokh', 'defaultcountry') === false) {
            set_config('defaultcountry', 'sa', 'local_qlogin_shomokh');
        }
        upgrade_plugin_savepoint(true, 2026081703, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081704) {
        upgrade_plugin_savepoint(true, 2026081704, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081705) {
        upgrade_plugin_savepoint(true, 2026081705, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081706) {
        $table = new xmldb_table('local_qlogin_shomokh_mail');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('purpose', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('provider', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('recipienthash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('recipienthint', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('messageid', XMLDB_TYPE_CHAR, '100');
        $table->add_field('idempotencykey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('httpstatus', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('lasterror', XMLDB_TYPE_CHAR, '255');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('idempotency_uix', XMLDB_INDEX_UNIQUE, ['idempotencykey']);
        $table->add_index('messageid_ix', XMLDB_INDEX_NOTUNIQUE, ['messageid']);
        $table->add_index('userpurpose_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'purpose']);
        $table->add_index('statustime_ix', XMLDB_INDEX_NOTUNIQUE, ['status', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_qlogin_shomokh_reset');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('channel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'email');
        $table->add_field('targethash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('state', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('tokenhash_ix', XMLDB_INDEX_NOTUNIQUE, ['tokenhash']);
        $table->add_index('userstate_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'state']);
        $table->add_index('targettime_ix', XMLDB_INDEX_NOTUNIQUE, ['targethash', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_qlogin_shomokh_alias');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('phone', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $table->add_field('verified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);
        $table->add_key('createdby_fk', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
        $table->add_index('phone_uix', XMLDB_INDEX_UNIQUE, ['phone']);
        $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        foreach (
            [
            'mailprovider' => 'resend', 'resendfromemail' => '', 'resendfromname' => 'Shomokh Al-Elm',
            'resendtimeout' => 8, 'mailmaxattempts' => 5, 'emailrecoveryenabled' => 1,
            'maillogretentiondays' => 90, 'legacydefaultcountrycode' => '966',
            ] as $name => $value
        ) {
            if (get_config('local_qlogin_shomokh', $name) === false) {
                set_config($name, $value, 'local_qlogin_shomokh');
            }
        }
        if (get_config('local_qlogin_shomokh', 'tokensecret') === false) {
            set_config('tokensecret', bin2hex(random_bytes(32)), 'local_qlogin_shomokh');
        }
        upgrade_plugin_savepoint(true, 2026081706, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081707) {
        // Student links from local_phoneverify are redirected at runtime. The
        // legacy integration is retired explicitly from the health page so its
        // completed states can be imported before its webhook is disabled.
        upgrade_plugin_savepoint(true, 2026081707, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081708) {
        // No schema change. This release moves the reminder to the top of the
        // page and adds privacy-minimal integration diagnostics.
        upgrade_plugin_savepoint(true, 2026081708, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081709) {
        // No schema change. Administrators are excluded from student prompts,
        // and the health page gains a session-bound end-to-end WhatsApp test.
        upgrade_plugin_savepoint(true, 2026081709, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081710) {
        // No schema change. Add a self-service verification summary and action
        // to each eligible user's own Moodle profile.
        upgrade_plugin_savepoint(true, 2026081710, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081711) {
        if (get_config('local_qlogin_shomokh', 'selfclaimenabled') === false) {
            set_config('selfclaimenabled', 1, 'local_qlogin_shomokh');
        }
        // No schema change. Existing accounts can now prove ownership and add
        // one verified phone alias while retaining the original Moodle userid.
        upgrade_plugin_savepoint(true, 2026081711, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081712) {
        // No schema change. Sign-in now resolves phone, unique email or username;
        // legacy grace starts at first login, and verified users may replace
        // their phone through an authenticated, re-verification flow.
        upgrade_plugin_savepoint(true, 2026081712, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081713) {
        // No schema change. Registration now reserves phones found in every
        // legacy source, and WhatsApp matches phone plus code when duplicate
        // historical verification rows exist.
        upgrade_plugin_savepoint(true, 2026081713, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081714) {
        // No schema change. International-number paste handling prevents a
        // repeated dialling code, and administrators can repair an affected
        // account without changing its userid or historical verification rows.
        upgrade_plugin_savepoint(true, 2026081714, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081719) {
        // The historical default covered manual accounts only. Include Moodle's
        // email self-registration accounts without overriding a custom list.
        $authtypes = get_config('local_qlogin_shomokh', 'authtypes');
        if (
            $authtypes === false || trim((string)$authtypes) === ''
                || trim((string)$authtypes) === 'manual'
        ) {
            set_config('authtypes', 'manual,email', 'local_qlogin_shomokh');
        }
        upgrade_plugin_savepoint(true, 2026081719, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081800) {
        // No schema change. Restore the proven in-card country-dropdown
        // positioning after an intermediate body-level layout regression.
        upgrade_plugin_savepoint(true, 2026081800, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081801) {
        // No schema change. Keep the dropdown attached to the telephone picker,
        // matching the previously verified layout instead of a page container.
        upgrade_plugin_savepoint(true, 2026081801, 'local', 'qlogin_shomokh');
    }
    if ($oldversion < 2026081812) {
        // No schema change. Use Moodle's OAuth provider discovery, make OAuth
        // opt-in to verification policy, and process one reminder per user.
        upgrade_plugin_savepoint(true, 2026081812, 'local', 'qlogin_shomokh');
    }
    return true;
}
