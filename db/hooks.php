<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Output hook subscriptions for Moodle 4.4 and later.
 *
 * @package   local_qlogin_shomokh
 * @copyright 2026 Shomokh Al-Elm
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

$callbacks = [];
if ((int)$CFG->version >= 2024042200) {
    $callbacks = [
        [
            'hook' => \core\hook\output\before_standard_head_html_generation::class,
            'callback' => [
                \local_qlogin_shomokh\hook\output::class,
                'before_standard_head_html_generation',
            ],
        ],
        [
            'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
            'callback' => [
                \local_qlogin_shomokh\hook\output::class,
                'before_standard_top_of_body_html_generation',
            ],
        ],
    ];
}
