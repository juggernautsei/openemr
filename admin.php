<?php

/**
 * Multi Site Administration entrypoint.
 *
 * Product build: always redirect to the authenticated /admin UI.
 * Legacy unauthenticated admin.php behavior is not used.
 *
 * @package OpenEMR
 * @link    https://www.open-emr.org
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

header('Location: admin/login.php', true, 302);
exit;
