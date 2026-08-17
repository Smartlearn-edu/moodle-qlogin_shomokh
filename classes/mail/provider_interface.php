<?php
// This file is part of Moodle - https://moodle.org/

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

