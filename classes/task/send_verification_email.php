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
 * Legacy background verification email compatibility task.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class send_verification_email extends \core\task\adhoc_task {
    /**
     * Execute task logic.
     */
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $record = $DB->get_record('local_qlogin_shomokh_verify', [
            'id' => (int)$data->recordid,
            'channel' => \local_qlogin_shomokh\verification::EMAIL,
        ]);
        $user = $record ? $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0]) : false;
        if (!$record || !$user || \local_qlogin_shomokh\verification::record_complete($record)) {
            return;
        }
        // Old 0.3.x tasks may contain a raw token. Ignore it and replace it with the deterministic 0.4 format.
        \local_qlogin_shomokh\verification::issue_email($user, false);
    }
}
