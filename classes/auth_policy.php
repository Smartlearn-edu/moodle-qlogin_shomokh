<?php
// This file is part of Moodle - https://moodle.org/

/** Authentication-method policy. @package local_qlogin_shomokh */
namespace local_qlogin_shomokh;

defined('MOODLE_INTERNAL') || die();

/** Provides one source of truth for authentication methods supported by the plugin. */
final class auth_policy {
    /** Authentication methods supported when the administrator has not configured a value. */
    private const DEFAULT_TYPES = ['manual', 'email'];

    /** Returns the configured, validated authentication method names. */
    public static function allowed_types(): array {
        $configured = get_config('local_qlogin_shomokh', 'authtypes');
        if ($configured === false || trim((string)$configured) === '') {
            return self::DEFAULT_TYPES;
        }

        $types = [];
        foreach (explode(',', (string)$configured) as $type) {
            $type = strtolower(trim($type));
            if (preg_match('/^[a-z][a-z0-9_]*$/', $type)) {
                $types[$type] = $type;
            }
        }
        return $types ? array_values($types) : self::DEFAULT_TYPES;
    }

    /** Whether one Moodle user belongs to the configured authentication flow. */
    public static function allows(\stdClass $user): bool {
        return in_array((string)($user->auth ?? ''), self::allowed_types(), true);
    }
}
