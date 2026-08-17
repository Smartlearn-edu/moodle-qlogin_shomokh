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
