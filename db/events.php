<?php
// This file is part of Moodle - https://moodle.org/
defined('MOODLE_INTERNAL') || die();
$observers = [
    ['eventname' => '\\core\\event\\user_created', 'callback' => '\\local_qlogin_shomokh\\observer::user_changed'],
    ['eventname' => '\\core\\event\\user_updated', 'callback' => '\\local_qlogin_shomokh\\observer::user_changed'],
    ['eventname' => '\\core\\event\\user_loggedin', 'callback' => '\\local_qlogin_shomokh\\observer::user_loggedin'],
];
