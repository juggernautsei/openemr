<?php

/**
 * Isolated tests for EnvFlag multi-site setup toggles.
 *
 * @package OpenEMR
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Common\Environment;

use OpenEMR\Common\Environment\EnvFlag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnvFlagTest extends TestCase
{
    private const FLAG = 'OPENEMR_TEST_ENV_FLAG_ISOLATED';

    protected function tearDown(): void
    {
        putenv(self::FLAG);
        unset($_SERVER[self::FLAG]);
        parent::tearDown();
    }

    public function testDisabledWhenUnset(): void
    {
        putenv(self::FLAG);
        unset($_SERVER[self::FLAG]);
        $this->assertFalse(EnvFlag::isEnabled(self::FLAG));
    }

    /**
     * @return list<array{0: string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function truthyProvider(): array
    {
        return [
            ['1'],
            ['true'],
            ['TRUE'],
            ['yes'],
            ['on'],
            [' On '],
        ];
    }

    #[DataProvider('truthyProvider')]
    public function testTruthyValuesFromGetenv(string $value): void
    {
        putenv(self::FLAG . '=' . $value);
        unset($_SERVER[self::FLAG]);
        $this->assertTrue(EnvFlag::isEnabled(self::FLAG));
    }

    /**
     * @return list<array{0: string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function falsyProvider(): array
    {
        return [
            ['0'],
            ['false'],
            ['no'],
            ['off'],
            [''],
            ['maybe'],
        ];
    }

    #[DataProvider('falsyProvider')]
    public function testFalsyValues(string $value): void
    {
        putenv(self::FLAG . '=' . $value);
        unset($_SERVER[self::FLAG]);
        $this->assertFalse(EnvFlag::isEnabled(self::FLAG));
    }

    public function testServerSuperglobalTruthy(): void
    {
        putenv(self::FLAG);
        $_SERVER[self::FLAG] = '1';
        $this->assertTrue(EnvFlag::isEnabled(self::FLAG));
    }
}
