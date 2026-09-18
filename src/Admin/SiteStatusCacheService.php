<?php

/**
 * Site Status Cache Service
 *
 * Caches multi-site dashboard status in the default site DB.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Admin;

use OpenEMR\Common\Database\QueryUtils;

class SiteStatusCacheService
{
    private const CACHE_KEY = 'sites_status';
    private const TABLE_NAME = 'admin_site_status_cache';
    private const DEFAULT_CACHE_TTL = 3600;

    /** @var array<string, mixed>|null */
    private ?array $memoryCache = null;

    public function __construct(private readonly int $cacheTtl = self::DEFAULT_CACHE_TTL)
    {
    }

    public function isCacheValid(): bool
    {
        $cacheData = $this->readCache();
        if ($cacheData === null) {
            return false;
        }
        $generatedAt = (int) ($cacheData['generated_at_timestamp'] ?? 0);
        return (time() - $generatedAt) < $this->cacheTtl;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readCache(): ?array
    {
        if ($this->memoryCache !== null) {
            return $this->memoryCache;
        }

        if (!$this->tableExists()) {
            return null;
        }

        try {
            $row = QueryUtils::querySingleRow(
                'SELECT cache_data, generated_at, cache_ttl_seconds FROM `' . self::TABLE_NAME . '` WHERE cache_key = ? LIMIT 1',
                [self::CACHE_KEY]
            );
        } catch (\Throwable) {
            return null;
        }

        if (empty($row['cache_data'])) {
            return null;
        }

        $sites = json_decode((string) $row['cache_data'], true);
        if (!is_array($sites)) {
            return null;
        }

        $generatedAtTimestamp = strtotime((string) $row['generated_at']) ?: 0;
        $this->memoryCache = [
            'generated_at' => $row['generated_at'],
            'generated_at_timestamp' => $generatedAtTimestamp,
            'cache_ttl_seconds' => (int) ($row['cache_ttl_seconds'] ?? $this->cacheTtl),
            'sites' => $sites,
        ];

        return $this->memoryCache;
    }

    /**
     * @param list<array<string, mixed>> $sitesInfo
     */
    public function writeCache(array $sitesInfo): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $jsonContent = json_encode($sitesInfo, JSON_UNESCAPED_SLASHES);
        if ($jsonContent === false) {
            return false;
        }

        try {
            QueryUtils::sqlStatementThrowException(
                'INSERT INTO `' . self::TABLE_NAME . '`
                    (cache_key, cache_data, generated_at, cache_ttl_seconds)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    cache_data = VALUES(cache_data),
                    generated_at = VALUES(generated_at),
                    cache_ttl_seconds = VALUES(cache_ttl_seconds)',
                [self::CACHE_KEY, $jsonContent, date('Y-m-d H:i:s'), $this->cacheTtl]
            );
            $this->memoryCache = null;
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function getCachedSitesInfo(): ?array
    {
        if (!$this->isCacheValid()) {
            return null;
        }
        $cacheData = $this->readCache();
        if ($cacheData === null) {
            return null;
        }
        /** @var list<array<string, mixed>>|null $sites */
        $sites = $cacheData['sites'] ?? null;
        return is_array($sites) ? $sites : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCacheMetadata(): ?array
    {
        $cacheData = $this->readCache();
        if ($cacheData === null) {
            return null;
        }
        return [
            'generated_at' => $cacheData['generated_at'] ?? 'Unknown',
            'generated_at_timestamp' => $cacheData['generated_at_timestamp'] ?? 0,
            'cache_ttl_seconds' => $cacheData['cache_ttl_seconds'] ?? $this->cacheTtl,
            'is_stale' => !$this->isCacheValid(),
        ];
    }

    public function invalidateCache(): bool
    {
        if (!$this->tableExists()) {
            $this->memoryCache = null;
            return true;
        }
        try {
            QueryUtils::sqlStatementThrowException(
                'DELETE FROM `' . self::TABLE_NAME . '` WHERE cache_key = ?',
                [self::CACHE_KEY]
            );
            $this->memoryCache = null;
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getCacheAge(): int
    {
        $cacheData = $this->readCache();
        if ($cacheData === null) {
            return PHP_INT_MAX;
        }
        return time() - (int) ($cacheData['generated_at_timestamp'] ?? 0);
    }

    public function getFormattedCacheAge(): string
    {
        $ageSeconds = $this->getCacheAge();
        if ($ageSeconds >= PHP_INT_MAX) {
            return 'No cache available';
        }
        if ($ageSeconds < 60) {
            return $ageSeconds . ' seconds ago';
        }
        $ageMinutes = (int) floor($ageSeconds / 60);
        if ($ageMinutes < 60) {
            return $ageMinutes . ' minute' . ($ageMinutes !== 1 ? 's' : '') . ' ago';
        }
        $ageHours = (int) floor($ageMinutes / 60);
        return $ageHours . ' hour' . ($ageHours !== 1 ? 's' : '') . ' ago';
    }

    public function tableExists(): bool
    {
        try {
            $row = QueryUtils::querySingleRow(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [self::TABLE_NAME]
            );
            return !empty($row['ok']);
        } catch (\Throwable) {
            return false;
        }
    }
}
