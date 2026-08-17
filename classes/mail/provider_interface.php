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

/** Transactional mail provider contract. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\mail;

defined('MOODLE_INTERNAL') || die();

/** Allows provider replacement without changing verification or recovery flows. */
interface provider_interface {
    /** Sends one message and never throws a provider exception to the caller. */
    public function send(message $message): result;

    /** Provider identifier stored in minimal audit rows. */
    public function name(): string;
}
