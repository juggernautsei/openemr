--
-- Admin site status cache (default site DB)
-- Used by multi-site administration dashboard to avoid opening every site DB on each page load.
--
-- Apply to the default site database, e.g.:
--   mysql -u ... -p default_db < sql/admin_site_status_cache.sql
--

CREATE TABLE IF NOT EXISTS `admin_site_status_cache` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(64) NOT NULL DEFAULT 'sites_status',
    `cache_data` LONGTEXT NOT NULL COMMENT 'JSON encoded site status data',
    `generated_at` DATETIME NOT NULL,
    `cache_ttl_seconds` INT(11) NOT NULL DEFAULT 3600 COMMENT 'Cache TTL in seconds',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `cache_key_unique` (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cached multi-site admin dashboard status';
