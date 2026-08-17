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

/** Bounded transactional email retry. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\task;

defined('MOODLE_INTERNAL') || die();

/** Retries by entity ID so task data never contains a raw one-time token. */
final class retry_email extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('task:retryemail', 'local_qlogin_shomokh');
    }

    public function execute(): void {
        $data = $this->get_custom_data();
        $purpose = clean_param((string)($data->purpose ?? ''), PARAM_ALPHANUMEXT);
        $entityid = (int)($data->entityid ?? 0);
        $attempt = max(1, (int)($data->attempt ?? 1));
        if (!$entityid) {
            return;
        }
        if ($purpose === 'verification') {
            \local_qlogin_shomokh\verification::retry_email($entityid, $attempt);
        } else if ($purpose === 'recovery') {
            \local_qlogin_shomokh\email_recovery::retry($entityid, $attempt);
        } else if ($purpose === 'reminder') {
            \local_qlogin_shomokh\verification::retry_reminder($entityid, $attempt);
        }
    }
}
