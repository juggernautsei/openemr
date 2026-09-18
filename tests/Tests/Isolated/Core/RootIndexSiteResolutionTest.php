<?php

/**
 * Mirrors index.php multi-site selection rules (no HTTP bootstrap).
 *
 * @package OpenEMR
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RootIndexSiteResolutionTest extends TestCase
{
    /**
     * Same resolution order as index.php after the multi-site fix.
     *
     * @param list<string> $validSiteIds
     */
    private function resolve(array $validSiteIds, ?string $querySite, ?string $httpHost): string
    {
        $count = count($validSiteIds);
        if ($count === 0) {
            throw new \RuntimeException('No valid sites found');
        }
        if ($count === 1) {
            return $validSiteIds[0];
        }

        $requested = $querySite;
        if (!is_string($requested) || $requested === '') {
            $requested = (is_string($httpHost) && $httpHost !== '') ? $httpHost : 'default';
        }
        if (in_array($requested, $validSiteIds, true)) {
            return $requested;
        }
        if (in_array('default', $validSiteIds, true)) {
            return 'default';
        }
        throw new \RuntimeException('Invalid site id');
    }

    /**
     * @return list<array{0: list<string>, 1: ?string, 2: ?string, 3: string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function cases(): array
    {
        $sites = ['default', 'kirnplasticsurgery'];
        return [
            'explicit site query' => [$sites, 'kirnplasticsurgery', 'sites.example.com', 'kirnplasticsurgery'],
            'generic vhost falls back to default' => [$sites, null, 'sites.affordablecustomehr.com', 'default'],
            'host named site directory' => [$sites, null, 'kirnplasticsurgery', 'kirnplasticsurgery'],
            'empty host uses default' => [$sites, '', '', 'default'],
            'single site ignores host' => [['onlysite'], null, 'whatever.example', 'onlysite'],
        ];
    }

    /**
     * @param list<string> $valid
     */
    #[DataProvider('cases')]
    public function testResolution(array $valid, ?string $query, ?string $host, string $expected): void
    {
        $this->assertSame($expected, $this->resolve($valid, $query, $host));
    }

    public function testInvalidWithoutDefaultThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->resolve(['alpha', 'beta'], null, 'not-a-site.example', 'x');
    }
}
