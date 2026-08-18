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
 * Result functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh\mail;

/**
 * Normalised result returned by every mail provider.
 */
final class result {
    /**
     * @var bool Whether the provider accepted the message.
     */
    public $accepted;
    /**
     * @var bool Whether retrying later is appropriate.
     */
    public $retryable;
    /**
     * @var string Provider delivery state.
     */
    public $status;
    /**
     * @var int HTTP status where applicable.
     */
    public $httpstatus;
    /**
     * @var string|null Provider message ID.
     */
    public $messageid;
    /**
     * @var string Safe diagnostic without credentials or full recipient data.
     */
    public $error;

    /**
     *   construct method.
     */
    public function __construct(
        bool $accepted,
        bool $retryable,
        string $status,
        int $httpstatus = 0,
        ?string $messageid = null,
        string $error = ''
    ) {
        $this->accepted = $accepted;
        $this->retryable = $retryable;
        $this->status = substr(clean_param($status, PARAM_ALPHANUMEXT), 0, 20);
        $this->httpstatus = $httpstatus;
        $this->messageid = $messageid === null ? null : substr(clean_param($messageid, PARAM_RAW_TRIMMED), 0, 100);
        $this->error = substr(clean_param($error, PARAM_TEXT), 0, 255);
    }
}
