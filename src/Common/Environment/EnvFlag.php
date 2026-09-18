<?php

/**
 * Read boolean-ish feature flags from process/server environment.
 *
 * Prefer env/ops configuration for high-risk toggles (e.g. multi-site setup)
 * so they are not editable from clinic Admin → Config.
 *
 * @package OpenEMR
 */

declare(strict_types=1);

namespace OpenEMR\Common\Environment;

final class EnvFlag
{
    /**
     * True when the named env var is set to 1/true/yes (case-insensitive).
     * Checks PHP getenv() and Apache/FPM-injected $_SERVER values.
     */
    public static function isEnabled(string $name): bool
    {
        $candidates = [];

        $fromServer = filter_input(INPUT_SERVER, $name);
        if (is_string($fromServer) && $fromServer !== '') {
            $candidates[] = $fromServer;
        } elseif (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
            $candidates[] = $_SERVER[$name];
        }

        $fromEnv = getenv($name);
        if (is_string($fromEnv) && $fromEnv !== '') {
            $candidates[] = $fromEnv;
        }

        foreach ($candidates as $value) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
        }

        return false;
    }
}
