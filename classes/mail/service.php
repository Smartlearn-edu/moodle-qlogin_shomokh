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

/** Transactional email service. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\mail;

defined('MOODLE_INTERNAL') || die();

/** Selects one provider, records minimal status and schedules bounded retries. */
final class service {
    /** Sends and records one message. */
    public static function send(message $message): result {
        $provider = self::provider();
        $result = $provider->send($message);
        self::record($message, $provider->name(), $result);
        return $result;
    }

    /** Sends one administrator-requested diagnostic message. */
    public static function send_test(string $recipient, int $userid): result {
        $recipient = \local_qlogin_shomokh\manager::normalise_email($recipient);
        if ($recipient === '') {
            return new result(false, false, 'failed', 0, null, 'invalid_recipient');
        }
        $site = format_string(get_site()->fullname);
        $key = 'test-' . substr(hash('sha256', $userid . ':' . $recipient . ':' . microtime(true)), 0, 48);
        return self::send(new message(
            $userid,
            $recipient,
            get_string('health:testsubject', 'local_qlogin_shomokh', $site),
            get_string('health:testbody', 'local_qlogin_shomokh'),
            'test',
            $key
        ));
    }

    /** Queues a bounded retry without putting a raw token in custom task data. */
    public static function queue_retry(string $purpose, int $entityid, int $attempt): bool {
        $maximum = max(1, min(10, (int)get_config('local_qlogin_shomokh', 'mailmaxattempts')));
        if ($attempt >= $maximum || !in_array($purpose, ['verification', 'recovery', 'reminder'], true)) {
            return false;
        }
        $task = new \local_qlogin_shomokh\task\retry_email();
        $task->set_custom_data([
            'purpose' => $purpose,
            'entityid' => $entityid,
            'attempt' => $attempt + 1,
        ]);
        $delay = min(HOURSECS, 60 * (2 ** max(0, $attempt - 1)));
        $task->set_next_run_time(time() + $delay);
        \core\task\manager::queue_adhoc_task($task, true);
        return true;
    }

    /** Returns a provider instance. */
    private static function provider(): provider_interface {
        return config::provider() === 'moodle' ? new moodle_provider() : new resend_provider();
    }

    /** Upserts a privacy-minimised delivery log by idempotency key. */
    private static function record(message $message, string $provider, result $result): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_qlogin_shomokh_mail')) {
            return;
        }
        $now = time();
        $record = $DB->get_record('local_qlogin_shomokh_mail', ['idempotencykey' => $message->idempotencykey]);
        if (!$record) {
            $record = (object)[
                'userid' => $message->userid,
                'purpose' => substr(clean_param($message->purpose, PARAM_ALPHANUMEXT), 0, 20),
                'provider' => $provider,
                'recipienthash' => config::hash_identifier($message->to),
                'recipienthint' => \local_qlogin_shomokh\manager::mask_email($message->to),
                'messageid' => $result->messageid,
                'idempotencykey' => $message->idempotencykey,
                'status' => $result->status,
                'httpstatus' => $result->httpstatus,
                'attempts' => 1,
                'lasterror' => $result->error === '' ? null : $result->error,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            try {
                $DB->insert_record('local_qlogin_shomokh_mail', $record);
            } catch (\dml_write_exception $exception) {
                // A concurrent idempotent attempt may have inserted the row first.
            }
            return;
        }
        $record->provider = $provider;
        $record->messageid = $result->messageid ?: $record->messageid;
        $record->status = $result->status;
        $record->httpstatus = $result->httpstatus;
        $record->attempts = (int)$record->attempts + 1;
        $record->lasterror = $result->error === '' ? null : $result->error;
        $record->timemodified = $now;
        $DB->update_record('local_qlogin_shomokh_mail', $record);
    }
}
