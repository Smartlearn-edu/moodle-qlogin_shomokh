<?php
// This file is part of Moodle - https://moodle.org/

/** Secret and provider configuration. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh\mail;

defined('MOODLE_INTERNAL') || die();

/** Resolves external secrets before database-backed settings. */
final class config {
    /** Returns the selected provider. */
    public static function provider(): string {
        $provider = (string)get_config('local_qlogin_shomokh', 'mailprovider');
        return in_array($provider, ['resend', 'moodle'], true) ? $provider : 'resend';
    }

    /** Returns [API key, source]. */
    public static function resend_api_key(): array {
        global $CFG;
        if (!empty($CFG->local_qlogin_shomokh_resendapikey)) {
            return [(string)$CFG->local_qlogin_shomokh_resendapikey, 'external'];
        }
        $environment = getenv('LOCAL_QLOGIN_SHOMOKH_RESEND_API_KEY');
        if (is_string($environment) && trim($environment) !== '') {
            return [trim($environment), 'external'];
        }
        $database = (string)get_config('local_qlogin_shomokh', 'resendapikey');
        return [$database, $database === '' ? 'missing' : 'database'];
    }

    /** Returns [webhook secret, source]. */
    public static function resend_webhook_secret(): array {
        global $CFG;
        if (!empty($CFG->local_qlogin_shomokh_resendwebhooksecret)) {
            return [(string)$CFG->local_qlogin_shomokh_resendwebhooksecret, 'external'];
        }
        $environment = getenv('LOCAL_QLOGIN_SHOMOKH_RESEND_WEBHOOK_SECRET');
        if (is_string($environment) && trim($environment) !== '') {
            return [trim($environment), 'external'];
        }
        $database = (string)get_config('local_qlogin_shomokh', 'resendwebhooksecret');
        return [$database, $database === '' ? 'missing' : 'database'];
    }

    /** Secret used to derive retryable one-time links without storing raw tokens in task data. */
    public static function token_secret(): string {
        global $CFG;
        if (!empty($CFG->local_qlogin_shomokh_tokensecret)) {
            return (string)$CFG->local_qlogin_shomokh_tokensecret;
        }
        $environment = getenv('LOCAL_QLOGIN_SHOMOKH_TOKEN_SECRET');
        if (is_string($environment) && trim($environment) !== '') {
            return trim($environment);
        }
        $secret = (string)get_config('local_qlogin_shomokh', 'tokensecret');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            set_config('tokensecret', $secret, 'local_qlogin_shomokh');
        }
        return $secret;
    }

    /** Derives a stable unguessable token for one entity generation. */
    public static function derive_token(string $purpose, int $entityid, int $userid, int $generation): string {
        $payload = $purpose . ':' . $entityid . ':' . $userid . ':' . $generation;
        return hash_hmac('sha256', $payload, self::token_secret());
    }

    /** Produces a non-reversible, installation-specific identifier for logs and throttling. */
    public static function hash_identifier(string $value): string {
        return hash_hmac('sha256', 'identifier:' . \core_text::strtolower(trim($value)), self::token_secret());
    }

    /** Whether Resend has enough information for sending. */
    public static function resend_ready(): bool {
        [$key] = self::resend_api_key();
        return $key !== '' && \local_qlogin_shomokh\manager::normalise_email(
            (string)get_config('local_qlogin_shomokh', 'resendfromemail')) !== '';
    }
}
