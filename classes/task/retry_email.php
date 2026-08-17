<?php
// This file is part of Moodle - https://moodle.org/

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

