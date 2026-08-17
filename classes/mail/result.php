<?php
// This file is part of Moodle - https://moodle.org/

/** Transactional email result. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\mail;

defined('MOODLE_INTERNAL') || die();

/** Normalised result returned by every mail provider. */
final class result {
    /** @var bool Whether the provider accepted the message. */
    public $accepted;
    /** @var bool Whether retrying later is appropriate. */
    public $retryable;
    /** @var string Provider delivery state. */
    public $status;
    /** @var int HTTP status where applicable. */
    public $httpstatus;
    /** @var string|null Provider message ID. */
    public $messageid;
    /** @var string Safe diagnostic without credentials or full recipient data. */
    public $error;

    public function __construct(bool $accepted, bool $retryable, string $status, int $httpstatus = 0,
            ?string $messageid = null, string $error = '') {
        $this->accepted = $accepted;
        $this->retryable = $retryable;
        $this->status = substr(clean_param($status, PARAM_ALPHANUMEXT), 0, 20);
        $this->httpstatus = $httpstatus;
        $this->messageid = $messageid === null ? null : substr(clean_param($messageid, PARAM_RAW_TRIMMED), 0, 100);
        $this->error = substr(clean_param($error, PARAM_TEXT), 0, 255);
    }
}
