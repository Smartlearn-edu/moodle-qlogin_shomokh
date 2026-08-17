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
 * Legacy output callbacks for Moodle 4.1 to 4.3.
 *
 * @package   local_qlogin_shomokh
 * @copyright 2026 Shomokh Al-Elm
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
if ((int)$CFG->version < 2024042200) {
    /**
     * Adds the verification-reminder stylesheet to standard Moodle pages.
     *
     * @return void
     */
    function local_qlogin_shomokh_before_standard_html_head() {
        global $PAGE;

        // Route any old student verification link to the unified centre.
        if (
            $PAGE->url->get_path() === '/local/phoneverify/verify.php'
                && \local_qlogin_shomokh\verification::available()
        ) {
            redirect(new moodle_url('/local/qlogin_shomokh/verify.php'));
        }

        if (
            in_array($PAGE->url->get_path(), [
            '/local/qlogin_shomokh/index.php',
            '/local/qlogin_shomokh/recover.php',
            '/local/qlogin_shomokh/link_existing.php',
            ], true)
        ) {
            return;
        }
        $assetversion = (int)get_config('local_qlogin_shomokh', 'version');
        $PAGE->requires->css(new moodle_url(
            '/local/qlogin_shomokh/qlogin_styles.css',
            ['v' => $assetversion]
        ));
    }

    /**
     * Adds a non-blocking email-verification reminder before the footer.
     *
     * @return string
     */
    function local_qlogin_shomokh_before_footer() {
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
            return '';
        }
        return \local_qlogin_shomokh\banner::render();
    }
}

/**
 * Adds a clear, self-service verification summary to the user's own profile.
 *
 * @param \core_user\output\myprofile\tree $tree Profile navigation tree.
 * @param stdClass $user Profile owner.
 * @param bool $iscurrentuser Whether the profile owner is the signed-in user.
 * @param stdClass|null $course Optional course context.
 * @return void
 */
function local_qlogin_shomokh_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    $user,
    $iscurrentuser,
    $course = null
) {
    if (
        !$iscurrentuser || !isloggedin() || isguestuser() || empty($user->id)
            || is_siteadmin($user->id)
            || has_capability('local/qlogin_shomokh:manage', context_system::instance())
    ) {
        return;
    }

    \local_qlogin_shomokh\verification::bootstrap_existing_user($user);

    if (\local_qlogin_shomokh\migration::can_self_claim($user)) {
        $categoryname = 'qloginshomokhverification';
        $tree->add_category(new \core_user\output\myprofile\category(
            $categoryname,
            get_string('profile:category', 'local_qlogin_shomokh'),
            null,
            'local-qlogin-profile-verification'
        ));
        $content = html_writer::tag('p', get_string('claim:profileintro', 'local_qlogin_shomokh'));
        $content .= html_writer::link(
            new moodle_url('/local/qlogin_shomokh/link_existing.php'),
            get_string('claim:bannerbutton', 'local_qlogin_shomokh'),
            ['class' => 'btn btn-primary local-qlogin-profile-verification__button']
        );
        $tree->add_node(new \core_user\output\myprofile\node(
            $categoryname,
            'qloginshomokhlinkphone',
            get_string('claim:title', 'local_qlogin_shomokh'),
            null,
            null,
            $content
        ));
        return;
    }

    if (!\local_qlogin_shomokh\verification::eligible($user)) {
        return;
    }

    $records = \local_qlogin_shomokh\verification::ensure_for_user($user);
    $items = [];
    $allcomplete = true;
    foreach (\local_qlogin_shomokh\verification::required_channels() as $channel) {
        $complete = isset($records[$channel])
            && \local_qlogin_shomokh\verification::record_complete($records[$channel]);
        $allcomplete = $allcomplete && $complete;
        $items[] = html_writer::tag('li', get_string('profile:channelstatus', 'local_qlogin_shomokh', (object)[
            'channel' => get_string('channel:' . $channel, 'local_qlogin_shomokh'),
            'status' => get_string($complete ? 'profile:verified' : 'profile:pending', 'local_qlogin_shomokh'),
        ]), ['class' => $complete ? 'text-success' : 'text-warning']);
    }

    if (!$items) {
        $items[] = html_writer::tag('li', get_string('verify:notrequired', 'local_qlogin_shomokh'), [
            'class' => 'text-muted',
        ]);
    }
    $content = html_writer::tag('ul', implode('', $items), [
        'class' => 'list-unstyled mb-3 local-qlogin-profile-verification__status',
    ]);
    $content .= html_writer::link(
        new moodle_url('/local/qlogin_shomokh/verify.php'),
        get_string(
            $allcomplete ? 'profile:viewverification' : 'profile:completeverification',
            'local_qlogin_shomokh'
        ),
        ['class' => 'btn btn-primary local-qlogin-profile-verification__button']
    );
    $content .= ' ' . html_writer::link(
        new moodle_url('/local/qlogin_shomokh/link_existing.php'),
        get_string('claim:changephone', 'local_qlogin_shomokh'),
        ['class' => 'btn btn-secondary local-qlogin-profile-verification__button']
    );

    $categoryname = 'qloginshomokhverification';
    $tree->add_category(new \core_user\output\myprofile\category(
        $categoryname,
        get_string('profile:category', 'local_qlogin_shomokh'),
        null,
        'local-qlogin-profile-verification'
    ));
    $tree->add_node(new \core_user\output\myprofile\node(
        $categoryname,
        'qloginshomokhverificationstatus',
        get_string('profile:title', 'local_qlogin_shomokh'),
        null,
        null,
        $content
    ));
}
