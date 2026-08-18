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
 * Scheduled verification processing tests.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Tests deterministic processing when more than one channel is expired.
 */
final class process_verifications_test extends \advanced_testcase {
    /**
     * A user with expired email and phone records is processed without a non-unique lookup.
     */
    public function test_two_expired_channels_do_not_cause_non_unique_record_debugging(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enabled', 0, 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user([
            'username' => 'expired.channels.user',
            'email' => 'expired-channels@example.test',
        ]);

        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requireemail', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual,email', 'local_qlogin_shomokh');
        set_config('graceperioddays', 1, 'local_qlogin_shomokh');
        set_config('expiredaction', 'remind', 'local_qlogin_shomokh');
        set_config('maxreminders', 0, 'local_qlogin_shomokh');

        $now = time();
        foreach ([verification::EMAIL, verification::PHONE] as $channel) {
            $DB->insert_record('local_qlogin_shomokh_verify', (object)[
                'userid' => $user->id,
                'channel' => $channel,
                'target' => $channel === verification::EMAIL ? $user->email : '14155550199',
                'tokenhash' => manager::hash_token('EXPIRED-' . $channel),
                'state' => verification::EXPIRED,
                'expiresat' => $now - HOURSECS,
                'verifiedat' => null,
                'lastsentat' => 0,
                'remindercount' => 0,
                'lastremindedat' => 0,
                'timecreated' => $now - (2 * DAYSECS),
                'timemodified' => $now,
            ]);
        }

        $task = new \local_qlogin_shomokh\task\process_verifications();
        $task->execute();

        $this->assertDebuggingNotCalled();
        $this->assertSame(2, $DB->count_records('local_qlogin_shomokh_verify', ['userid' => $user->id]));
    }
}
