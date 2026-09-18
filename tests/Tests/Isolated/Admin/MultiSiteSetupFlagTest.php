<?php

/**
 * Product multi-site setup flag contract tests.
 *
 * @package OpenEMR
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Admin;

use OpenEMR\Common\Environment\EnvFlag;
use PHPUnit\Framework\TestCase;

class MultiSiteSetupFlagTest extends TestCase
{
    private const MULTISITE = 'OPENEMR_ALLOW_MULTISITE_SETUP';
    private const CLONING = 'OPENEMR_ALLOW_CLONING_SETUP';

    protected function tearDown(): void
    {
        putenv(self::MULTISITE);
        putenv(self::CLONING);
        unset($_SERVER[self::MULTISITE], $_SERVER[self::CLONING]);
        parent::tearDown();
    }

    public function testMultiSiteSetupFlagDefaultsOff(): void
    {
        putenv(self::MULTISITE);
        unset($_SERVER[self::MULTISITE]);
        $this->assertFalse(EnvFlag::isEnabled(self::MULTISITE));
    }

    public function testMultiSiteSetupFlagCanEnable(): void
    {
        putenv(self::MULTISITE . '=1');
        unset($_SERVER[self::MULTISITE]);
        $this->assertTrue(EnvFlag::isEnabled(self::MULTISITE));
    }

    public function testCloningFlagIndependent(): void
    {
        putenv(self::MULTISITE . '=1');
        putenv(self::CLONING);
        unset($_SERVER[self::CLONING]);
        $this->assertTrue(EnvFlag::isEnabled(self::MULTISITE));
        $this->assertFalse(EnvFlag::isEnabled(self::CLONING));
    }
}
