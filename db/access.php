<?php
// This file is part of Moodle - https://moodle.org/
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'local/qlogin_shomokh:manage' => [
        'riskbitmask' => RISK_PERSONAL | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
];
