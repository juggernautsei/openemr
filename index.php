<?php

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.


/**
 * Set the site ID if required.
 *
 * This must be done before any database access is attempted.
 * Use an IIFE to ensure no variables leak.
 */

(function (): void {
    // Any directory name in sites that contains a sqlconf.php can be a site id.
    $sites_dirs = glob('sites/*', GLOB_ONLYDIR) ?: [];
    $valid_site_ids = array_map(
        basename(...),
        array_filter($sites_dirs, fn($d): bool => is_file("{$d}/sqlconf.php"))
    );

    switch (count($valid_site_ids)) {
        case 0:
            throw new RuntimeException('No valid sites found');
        // Often there's only one valid request id, so we can ignore input.
        case 1:
            $site_id = $valid_site_ids[0];
            break;
        default:
            // Prefer explicit ?site=, then host-named site dirs, then default hub.
            // A generic vhost (e.g. sites.example.com) is not a site id — match globals.php
            // behavior and fall back to default when the host is not a configured site.
            $requested = filter_input(INPUT_GET, 'site');
            if (!is_string($requested) || $requested === '') {
                $host = filter_input(INPUT_SERVER, 'HTTP_HOST');
                $requested = (is_string($host) && $host !== '') ? $host : 'default';
            }
            if (in_array($requested, $valid_site_ids, true)) {
                $site_id = $requested;
            } elseif (in_array('default', $valid_site_ids, true)) {
                $site_id = 'default';
            } else {
                throw new RuntimeException(
                    'Invalid site id. Use ?site=<id> with one of: ' . implode(', ', $valid_site_ids)
                );
            }
    }
    require_once "sites/{$site_id}/sqlconf.php";
    /** @var int $config Defined in sqlconf.php */
    header('Location: ' . ($config === 1 ? 'interface/login/login.php' : 'setup.php') . "?site=$site_id");
})();
