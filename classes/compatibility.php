<?php
// This file is part of Moodle - https://moodle.org/

/** Compatibility bridge for the retired local_phoneverify plugin. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Safely imports completed legacy states before disabling the old integration. */
final class compatibility {
    /** Returns a privacy-safe summary of the old plugin state. */
    public static function legacy_status(): array {
        global $DB;

        $present = $DB->get_manager()->table_exists('local_phoneverify');
        return [
            'present' => $present,
            'enabled' => $present && (bool)get_config('local_phoneverify', 'enabled'),
            'records' => $present ? $DB->count_records('local_phoneverify') : 0,
        ];
    }

    /** Whether the unified WhatsApp endpoint has all required configuration. */
    public static function unified_whatsapp_ready(): bool {
        return manager::normalise_phone((string)get_config('local_qlogin_shomokh', 'businessnumber')) !== ''
            && preg_match('/^\d+$/', (string)get_config('local_qlogin_shomokh', 'businessphonenumberid')) === 1
            && trim((string)get_config('local_qlogin_shomokh', 'webhookverifytoken')) !== ''
            && trim((string)get_config('local_qlogin_shomokh', 'webhookappsecret')) !== '';
    }

    /**
     * Imports matching completed states and disables the old webhook and task flow.
     *
     * Legacy records are deliberately retained for rollback and audit. The caller
     * must require the manage capability and a valid sesskey.
     *
     * @return array Counts of imported and skipped records.
     */
    public static function retire_legacy(): array {
        global $DB;

        $status = self::legacy_status();
        if (!$status['present']) {
            return ['imported' => 0, 'skipped' => 0];
        }
        if (!self::unified_whatsapp_ready()) {
            throw new \moodle_exception('health:legacywhatsappmissing', 'local_qlogin_shomokh');
        }

        $imported = 0;
        $skipped = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach ($DB->get_records('local_phoneverify') as $legacy) {
            if (!in_array((string)$legacy->state, [verification::VERIFIED, verification::WAIVED], true)) {
                continue;
            }
            $user = $DB->get_record('user', ['id' => $legacy->userid, 'deleted' => 0]);
            if (!$user) {
                $skipped++;
                continue;
            }
            $phone = manager::normalise_phone((string)$legacy->phone);
            $currentphone = migration::phone_for_user($user);
            if ($phone === '' || $phone !== $currentphone) {
                $skipped++;
                continue;
            }
            $record = verification::ensure_channel($user, verification::PHONE);
            if (verification::record_complete($record)) {
                continue;
            }
            $record->state = (string)$legacy->state;
            $record->tokenhash = '';
            $record->verifiedat = $legacy->verifiedat ?: null;
            $record->timemodified = time();
            $DB->update_record('local_qlogin_shomokh_verify', $record);
            if ($record->state === verification::VERIFIED) {
                migration::mark_alias_verified((int)$user->id, $phone);
            }
            $imported++;
        }
        set_config('enabled', 0, 'local_phoneverify');
        $transaction->allow_commit();
        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
