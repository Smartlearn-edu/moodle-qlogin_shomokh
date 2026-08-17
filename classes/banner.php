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

/** Unified non-blocking reminder banner. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

final class banner {
    /** Returns reminder HTML for required incomplete channels. */
    public static function render(): string {
        global $PAGE, $USER;
        if (
            !isloggedin() || isguestuser() || empty($USER->id) || is_siteadmin($USER->id)
                || has_capability('local/qlogin_shomokh:manage', \context_system::instance())
                || !verification::available()
        ) {
            return '';
        }
        if (
            in_array($PAGE->url->get_path(), [
            '/local/qlogin_shomokh/verify.php',
            '/local/qlogin_shomokh/link_existing.php',
            ], true)
        ) {
            return '';
        }
        verification::bootstrap_existing_user($USER);
        if (migration::can_self_claim($USER)) {
            $record = verification::get((int)$USER->id, verification::PHONE);
            $deadline = $record ? (int)$record->expiresat : time() + verification::grace_seconds();
            $key = $record && ($record->state === verification::EXPIRED || $deadline < time())
                ? 'claim:bannerexpired' : 'claim:banner';
            return \html_writer::tag(
                'aside',
                \html_writer::span(
                    get_string($key, 'local_qlogin_shomokh', userdate($deadline)),
                    'local-qlogin-shomokh-banner__message'
                )
                    . \html_writer::link(
                        new \moodle_url('/local/qlogin_shomokh/link_existing.php'),
                        get_string('claim:bannerbutton', 'local_qlogin_shomokh'),
                        ['class' => 'btn btn-primary local-qlogin-shomokh-banner__button']
                    ),
                ['class' => 'local-qlogin-shomokh-banner', 'role' => 'status']
            );
        }
        $records = verification::ensure_for_user($USER);
        $missing = [];
        $missingchannels = [];
        $expired = false;
        $deadline = 0;
        foreach ($records as $channel => $record) {
            if (!verification::record_complete($record)) {
                $missing[] = get_string('channel:' . $channel, 'local_qlogin_shomokh');
                $missingchannels[] = $channel;
                $expired = $expired || $record->state === verification::EXPIRED || $record->expiresat < time();
                $deadline = $deadline === 0 ? $record->expiresat : min($deadline, $record->expiresat);
            }
        }
        if (!$missing) {
            return '';
        }
        $values = (object)['channels' => implode('، ', $missing), 'deadline' => userdate($deadline)];
        $key = $expired ? 'banner:expired:' . self::safe_action() : 'banner:pending';
        $phoneonly = $missingchannels === [verification::PHONE];
        $button = $phoneonly ? 'banner:verifyphone' : 'banner:continue';
        return \html_writer::tag(
            'aside',
            \html_writer::span(
                get_string($key, 'local_qlogin_shomokh', $values),
                'local-qlogin-shomokh-banner__message'
            )
                . \html_writer::link(
                    new \moodle_url('/local/qlogin_shomokh/verify.php'),
                    get_string($button, 'local_qlogin_shomokh'),
                    ['class' => 'btn btn-primary local-qlogin-shomokh-banner__button']
                ),
            ['class' => 'local-qlogin-shomokh-banner', 'role' => 'status']
        );
    }

    /** Returns a supported expiry action. */
    private static function safe_action(): string {
        $action = (string)get_config('local_qlogin_shomokh', 'expiredaction');
        return in_array($action, ['remind', 'courses', 'suspend'], true) ? $action : 'remind';
    }
}
