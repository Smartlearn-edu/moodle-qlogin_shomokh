<?php
// This file is part of Moodle - https://moodle.org/

/** Transactional email value object. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\mail;

defined('MOODLE_INTERNAL') || die();

/** Contains one provider-neutral transactional email. */
final class message {
    /** @var int|null Moodle user ID when known. */
    public $userid;
    /** @var string Recipient address. */
    public $to;
    /** @var string Localised subject. */
    public $subject;
    /** @var string Plain-text body. */
    public $text;
    /** @var string Message purpose. */
    public $purpose;
    /** @var string Stable provider idempotency key. */
    public $idempotencykey;

    public function __construct(?int $userid, string $to, string $subject, string $text,
            string $purpose, string $idempotencykey) {
        $this->userid = $userid;
        $this->to = $to;
        $this->subject = $subject;
        $this->text = $text;
        $this->purpose = $purpose;
        $this->idempotencykey = substr(clean_param($idempotencykey, PARAM_ALPHANUMEXT), 0, 64);
    }
}

