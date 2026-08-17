<?php
// This file is part of Moodle - https://moodle.org/

/** Signed Resend delivery webhook. @package local_qlogin_shomokh */
define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);
require_once('../../config.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    local_qlogin_shomokh_resend_response(405, 'Method not allowed');
}
$rawbody = file_get_contents('php://input');
if ($rawbody === false || strlen($rawbody) > 1048576) {
    local_qlogin_shomokh_resend_response(413, 'Payload too large');
}
$id = clean_param($_SERVER['HTTP_SVIX_ID'] ?? '', PARAM_RAW_TRIMMED);
$timestamp = clean_param($_SERVER['HTTP_SVIX_TIMESTAMP'] ?? '', PARAM_RAW_TRIMMED);
$signature = clean_param($_SERVER['HTTP_SVIX_SIGNATURE'] ?? '', PARAM_RAW_TRIMMED);
$payload = \local_qlogin_shomokh\mail\webhook::verify($rawbody, $id, $timestamp, $signature);
if (!$payload) {
    local_qlogin_shomokh_resend_response(401, 'Invalid signature');
}
\local_qlogin_shomokh\mail\webhook::apply($payload);
local_qlogin_shomokh_resend_response(200, 'OK');

/** Sends a minimal response without leaking configuration or payload data. */
function local_qlogin_shomokh_resend_response(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}
