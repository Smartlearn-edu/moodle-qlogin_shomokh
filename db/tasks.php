<?php
// This file is part of Moodle - https://moodle.org/
defined('MOODLE_INTERNAL') || die();
$tasks = [
    [
        'classname' => '\\local_qlogin_shomokh\\task\\process_verifications',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
