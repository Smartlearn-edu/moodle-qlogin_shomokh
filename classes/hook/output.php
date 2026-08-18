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

namespace local_qlogin_shomokh\hook;

/**
 * Injects the small reminder stylesheet and non-blocking banner.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class output {
    /**
     * Callback before standard head HTML generation.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook Output hook.
     * @return void
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $PAGE;

        // Existing bookmarks and the old plugin's links must lead to the one
        // unified centre. This runs before output, so Moodle can redirect safely.
        if (
            $PAGE->url->get_path() === '/local/phoneverify/verify.php'
                && \local_qlogin_shomokh\verification::available()
        ) {
            redirect(new \moodle_url('/local/qlogin_shomokh/verify.php'));
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
        $url = new \moodle_url('/local/qlogin_shomokh/qlogin_styles.css', ['v' => $assetversion]);
        $hook->add_html(\html_writer::empty_tag('link', [
            'rel' => 'stylesheet',
            'href' => $url->out(false),
        ]));
    }

    /**
     * Places the reminder where it is visible immediately, before dashboard
     * content that can be several screens tall.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook Output hook.
     * @return void
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
            return;
        }
        $hook->add_html(\local_qlogin_shomokh\banner::render());
    }

    /**
     * Retained for source compatibility; Moodle 4.4+ uses the top-of-body hook.
     *
     * @param \core\hook\output\before_footer_html_generation $hook Output hook.
     * @return void
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
            return;
        }
        $hook->add_html(\local_qlogin_shomokh\banner::render());
    }
}
