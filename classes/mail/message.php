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
 * Message functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh\mail;

/**
 * Contains one provider-neutral transactional email.
 */
final class message {
    /**
     * @var int|null Moodle user ID when known.
     */
    public $userid;
    /**
     * @var string Recipient address.
     */
    public $to;
    /**
     * @var string Localised subject.
     */
    public $subject;
    /**
     * @var string Plain-text body.
     */
    public $text;
    /**
     * @var string Message purpose.
     */
    public $purpose;
    /**
     * @var string Stable provider idempotency key.
     */
    public $idempotencykey;

    /**
     *   construct method.
     */
    public function __construct(
        ?int $userid,
        string $to,
        string $subject,
        string $text,
        string $purpose,
        string $idempotencykey
    ) {
        $this->userid = $userid;
        $this->to = $to;
        $this->subject = $subject;
        $this->text = $text;
        $this->purpose = $purpose;
        $this->idempotencykey = substr(clean_param($idempotencykey, PARAM_ALPHANUMEXT), 0, 64);
    }
}
