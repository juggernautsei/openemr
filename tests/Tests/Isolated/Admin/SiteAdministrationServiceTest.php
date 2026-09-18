<?php

/**
 * Isolated tests for multi-site SiteAdministrationService.
 *
 * @package OpenEMR
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Admin;

use OpenEMR\Admin\SiteAdministrationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SiteAdministrationServiceTest extends TestCase
{
    private string $tempRoot = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . '/oe-admin-sites-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->tempRoot !== '' && is_dir($this->tempRoot)) {
            $this->removeTree($this->tempRoot);
        }
        parent::tearDown();
    }

    private function service(int $db = 543, int $acl = 14, int $patch = 0): SiteAdministrationService
    {
        return new SiteAdministrationService($this->tempRoot, $db, $acl, $patch);
    }

    private function writeSite(string $siteId, string $sqlconfBody): void
    {
        $dir = $this->tempRoot . '/' . $siteId;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/sqlconf.php', $sqlconfBody);
    }

    private function removeTree(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testIsValidSiteNameAcceptsSafeIds(): void
    {
        $svc = $this->service();
        $this->assertTrue($svc->isValidSiteName('default'));
        $this->assertTrue($svc->isValidSiteName('primevascular'));
        $this->assertTrue($svc->isValidSiteName('clinic-1.example'));
    }

    public function testIsValidSiteNameRejectsUnsafeIds(): void
    {
        $svc = $this->service();
        $this->assertFalse($svc->isValidSiteName(''));
        $this->assertFalse($svc->isValidSiteName('../etc'));
        $this->assertFalse($svc->isValidSiteName('site name'));
        $this->assertFalse($svc->isValidSiteName('a/b'));
    }

    public function testDiscoverSitesIgnoresInvalidAndIncompleteDirs(): void
    {
        $this->writeSite('default', "<?php\n\$config = 1;\n");
        $this->writeSite('clinic-a', "<?php\n\$config = 1;\n");
        mkdir($this->tempRoot . '/no-sqlconf', 0777, true);
        mkdir($this->tempRoot . '/bad name', 0777, true);
        file_put_contents($this->tempRoot . '/notadir.txt', 'x');
        // hidden / CVS ignored
        mkdir($this->tempRoot . '/.hidden', 0777, true);
        file_put_contents($this->tempRoot . '/.hidden/sqlconf.php', "<?php\n\$config=1;\n");
        mkdir($this->tempRoot . '/CVS', 0777, true);
        file_put_contents($this->tempRoot . '/CVS/sqlconf.php', "<?php\n\$config=1;\n");

        $sites = $this->service()->discoverSites();
        $this->assertSame(['clinic-a' => 'clinic-a', 'default' => 'default'], $sites);
    }

    public function testDiscoverSitesEmptyWhenBaseMissing(): void
    {
        $svc = new SiteAdministrationService($this->tempRoot . '/does-not-exist', 1, 1, 0);
        $this->assertSame([], $svc->discoverSites());
    }

    public function testGetSiteInfoNeedsSetupWhenConfigEmpty(): void
    {
        $this->writeSite('newclinic', "<?php\n\$config = 0;\n");
        $info = $this->service()->getSiteInfo(
            'newclinic',
            $this->tempRoot . '/newclinic/sqlconf.php'
        );
        $this->assertTrue($info['needs_setup']);
        $this->assertFalse($info['is_current']);
        $this->assertSame('newclinic', $info['site_id']);
    }

    public function testGetSiteInfoConnectErrorWhenCredentialsIncomplete(): void
    {
        // config set but host/login/pass/dbase not strings
        $this->writeSite(
            'broken',
            "<?php\n\$config = 1;\n\$host = null;\n\$login = null;\n\$pass = null;\n\$dbase = null;\n"
        );
        $info = $this->service()->getSiteInfo(
            'broken',
            $this->tempRoot . '/broken/sqlconf.php'
        );
        $this->assertFalse($info['needs_setup']);
        $this->assertSame('MySQL connect failed', $info['error']);
        $this->assertFalse($info['is_current']);
    }

    public function testGetAllSitesInfoAggregatesDiscoveredSites(): void
    {
        $this->writeSite('alpha', "<?php\n\$config = 0;\n");
        $this->writeSite('beta', "<?php\n\$config = 0;\n");
        $all = $this->service()->getAllSitesInfo();
        $this->assertCount(2, $all);
        $ids = array_column($all, 'site_id');
        $this->assertSame(['alpha', 'beta'], $ids);
        $this->assertTrue($all[0]['needs_setup']);
        $this->assertTrue($all[1]['needs_setup']);
    }

    /**
     * @return list<array{0: int, 1: int, 2: int, 3: bool, 4: string, 5: bool}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function versionStatusProvider(): array
    {
        // app db=543 acl=14 patch=0
        // siteDb, siteAcl, sitePatch, requires_upgrade, upgrade_type, is_current
        return [
            'current exact match' => [543, 14, 0, false, '', true],
            'current higher site acl ok' => [543, 20, 0, false, '', true],
            'database upgrade' => [500, 14, 0, true, 'database', false],
            'database wins over acl' => [500, 1, 0, true, 'database', false],
            'acl upgrade' => [543, 10, 0, true, 'acl', false],
            'patch upgrade' => [543, 14, 2, true, 'patch', false],
            'patch zero vs nonzero' => [543, 14, 1, true, 'patch', false],
        ];
    }

    #[DataProvider('versionStatusProvider')]
    public function testEvaluateVersionStatus(
        int $siteDb,
        int $siteAcl,
        int $sitePatch,
        bool $requiresUpgrade,
        string $upgradeType,
        bool $isCurrent
    ): void {
        $result = $this->service(543, 14, 0)->evaluateVersionStatus($siteDb, $siteAcl, $sitePatch);
        $this->assertSame($requiresUpgrade, $result['requires_upgrade']);
        $this->assertSame($upgradeType, $result['upgrade_type']);
        $this->assertSame($isCurrent, $result['is_current']);
    }

    public function testEvaluateVersionStatusUsesConstructorAppVersions(): void
    {
        $svc = $this->service(100, 5, 3);
        $current = $svc->evaluateVersionStatus(100, 5, 3);
        $this->assertTrue($current['is_current']);

        $dbUpgrade = $svc->evaluateVersionStatus(99, 5, 3);
        $this->assertSame('database', $dbUpgrade['upgrade_type']);
    }
}
