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
