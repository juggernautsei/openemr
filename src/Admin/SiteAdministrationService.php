<?php

/**
 * Site Administration Service
 *
 * Discovers sites under sites/ and reports version/status with short-lived DB connections.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Admin;

class SiteAdministrationService
{
    private const CONNECT_TIMEOUT = 5;
    private const READ_TIMEOUT = 10;
    private const VALID_SITE_NAME_PATTERN = '/^[a-zA-Z0-9._-]+$/';

    private readonly int $appDatabaseVersion;
    private readonly int $appAclVersion;
    private readonly int $appRealPatch;

    /**
     * @param string $sitesBaseDir Absolute path to the sites/ directory
     * @param int|null $appDatabaseVersion Application $v_database from version.php
     * @param int|null $appAclVersion Application $v_acl from version.php
     * @param int|null $appRealPatch Application $v_realpatch from version.php
     */
    public function __construct(
        private readonly string $sitesBaseDir,
        ?int $appDatabaseVersion = null,
        ?int $appAclVersion = null,
        ?int $appRealPatch = null
    ) {
        // Prefer explicit constructor args; fall back to version.php globals if present.
        global $v_database, $v_acl, $v_realpatch;
        $this->appDatabaseVersion = $appDatabaseVersion ?? (int) ($v_database ?? 0);
        $this->appAclVersion = $appAclVersion ?? (int) ($v_acl ?? 0);
        $this->appRealPatch = $appRealPatch ?? (int) ($v_realpatch ?? 0);
    }

    public function isValidSiteName(string $siteName): bool
    {
        return $siteName !== '' && preg_match(self::VALID_SITE_NAME_PATTERN, $siteName) === 1;
    }

    /**
     * @return array<string, string>
     */
    public function discoverSites(): array
    {
        $sites = [];
        if (!is_dir($this->sitesBaseDir)) {
            return $sites;
        }

        $handle = opendir($this->sitesBaseDir);
        if ($handle === false) {
            return $sites;
        }

        while (false !== ($filename = readdir($handle))) {
            if (str_starts_with($filename, '.') || $filename === 'CVS') {
                continue;
            }
            if (!$this->isValidSiteName($filename)) {
                continue;
            }
            $siteDir = $this->sitesBaseDir . '/' . $filename;
            if (!is_dir($siteDir) || !is_file($siteDir . '/sqlconf.php')) {
                continue;
            }
            $sites[$filename] = $filename;
        }

        closedir($handle);
        ksort($sites);

        return $sites;
    }

    /**
     * @return array{
     *   site_id: string,
     *   db_name: string,
     *   site_name: string,
     *   version: string,
     *   needs_setup: bool,
     *   error: string,
     *   requires_upgrade: bool,
     *   upgrade_type: string,
     *   is_current: bool,
     *   app_database: int,
     *   site_database: int,
     *   app_acl: int,
     *   site_acl: int,
     *   app_patch: int,
     *   site_patch: int
     * }
     */
    public function getSiteInfo(string $siteName, string $sqlconfPath): array
    {
        $siteInfo = [
            'site_id' => $siteName,
            'db_name' => '',
            'site_name' => '',
            'version' => 'Unknown',
            'needs_setup' => false,
            'error' => '',
            'requires_upgrade' => false,
            'upgrade_type' => '',
            'is_current' => false,
            'app_database' => $this->appDatabaseVersion,
            'site_database' => 0,
            'app_acl' => $this->appAclVersion,
            'site_acl' => 0,
            'app_patch' => $this->appRealPatch,
            'site_patch' => 0,
        ];

        $config = null;
        $host = null;
        $login = null;
        $pass = null;
        $dbase = null;
        $port = 3306;

        include $sqlconfPath;

        if (empty($config)) {
            $siteInfo['needs_setup'] = true;
            return $siteInfo;
        }

        $siteInfo['db_name'] = is_string($dbase) ? $dbase : '';

        if (!is_string($host) || !is_string($login) || !is_string($pass) || !is_string($dbase)) {
            $siteInfo['error'] = 'MySQL connect failed';
            return $siteInfo;
        }

        $dbh = $this->connectToSiteDb($host, $login, $pass, $dbase, (int) $port);
        if ($dbh === false) {
            $siteInfo['error'] = 'MySQL connect failed';
            return $siteInfo;
        }

        $row = $this->sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'openemr_name' LIMIT 1", $dbh);
        $siteInfo['site_name'] = $row['gl_value'] ?? '';

        $row = $this->sqlQuery("SHOW TABLES LIKE 'version'", $dbh);
        if ($row === null) {
            mysqli_close($dbh);
            $siteInfo['error'] = 'Version table missing';
            return $siteInfo;
        }

        $row = $this->sqlQuery('SELECT * FROM version LIMIT 1', $dbh);
        if ($row === null) {
            mysqli_close($dbh);
            $siteInfo['error'] = 'Version row missing';
            return $siteInfo;
        }

        $patchText = '';
        if (!empty($row['v_realpatch']) && (int) $row['v_realpatch'] !== 0) {
            $patchText = ' (' . $row['v_realpatch'] . ')';
        }

        $siteInfo['version'] = ($row['v_major'] ?? '') . '.' . ($row['v_minor'] ?? '') . '.' .
            ($row['v_patch'] ?? '') . ($row['v_tag'] ?? '') . $patchText;

        $databaseVersion = (int) ($row['v_database'] ?? 0);
        $databaseAcl = (int) ($row['v_acl'] ?? 0);
        $databasePatch = (int) ($row['v_realpatch'] ?? 0);

        $siteInfo['site_database'] = $databaseVersion;
        $siteInfo['site_acl'] = $databaseAcl;
        $siteInfo['site_patch'] = $databasePatch;

        $currency = $this->evaluateVersionStatus($databaseVersion, $databaseAcl, $databasePatch);
        $siteInfo['requires_upgrade'] = $currency['requires_upgrade'];
        $siteInfo['upgrade_type'] = $currency['upgrade_type'];
        $siteInfo['is_current'] = $currency['is_current'];

        mysqli_close($dbh);

        return $siteInfo;
    }

    /**
     * Compare a site DB version row to the application version (legacy admin.php rules).
     *
     * Current when: app database == site database, app ACL <= site ACL, app patch == site patch.
     *
     * @return array{requires_upgrade: bool, upgrade_type: string, is_current: bool}
     */
    public function evaluateVersionStatus(int $siteDatabase, int $siteAcl, int $sitePatch): array
    {
        if ($this->appDatabaseVersion !== $siteDatabase) {
            return [
                'requires_upgrade' => true,
                'upgrade_type' => 'database',
                'is_current' => false,
            ];
        }

        if ($this->appAclVersion > $siteAcl) {
            return [
                'requires_upgrade' => true,
                'upgrade_type' => 'acl',
                'is_current' => false,
            ];
        }

        if ($this->appRealPatch !== $sitePatch) {
            return [
                'requires_upgrade' => true,
                'upgrade_type' => 'patch',
                'is_current' => false,
            ];
        }

        return [
            'requires_upgrade' => false,
            'upgrade_type' => '',
            'is_current' => true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAllSitesInfo(): array
    {
        $sitesInfo = [];
        foreach ($this->discoverSites() as $siteName) {
            $sitesInfo[] = $this->getSiteInfo(
                $siteName,
                $this->sitesBaseDir . '/' . $siteName . '/sqlconf.php'
            );
        }

        return $sitesInfo;
    }

    private function connectToSiteDb(
        string $host,
        string $login,
        string $pass,
        string $dbase,
        int $port
    ): \mysqli|false {
        try {
            $dbh = mysqli_init();
            if (!$dbh instanceof \mysqli) {
                return false;
            }
            mysqli_options($dbh, MYSQLI_OPT_CONNECT_TIMEOUT, self::CONNECT_TIMEOUT);
            mysqli_options($dbh, MYSQLI_OPT_READ_TIMEOUT, self::READ_TIMEOUT);
            if (!@mysqli_real_connect($dbh, $host, $login, $pass, $dbase, $port)) {
                return false;
            }
            return $dbh;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sqlQuery(string $statement, \mysqli $link): ?array
    {
        $result = mysqli_query($link, $statement);
        if (!$result instanceof \mysqli_result) {
            return null;
        }
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        mysqli_free_result($result);

        return is_array($row) ? $row : null;
    }
}
