-- IPA Data Center v1.3 / 2406 Dylib runtime access.
-- MySQL 5.7 compatible. Mirrors release/sql/2026092406_dylib_runtime_access.sql
-- so clean installs and online upgrades converge on the same schema.

CREATE TABLE IF NOT EXISTS `fa_ipa_app_identity` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `bundle_id` varchar(255) NOT NULL DEFAULT '',
  `executable` varchar(255) NOT NULL DEFAULT '',
  `macho_uuid` varchar(64) NOT NULL DEFAULT '',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asset_identity` (`asset_id`),
  KEY `idx_runtime_identity` (`bundle_id`(64),`executable`(64),`macho_uuid`(36))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Parsed main executable runtime identity';

CREATE TABLE IF NOT EXISTS `fa_dylib_runtime_config` (
  `id` int unsigned NOT NULL DEFAULT '1',
  `config_version` int unsigned NOT NULL DEFAULT '1',
  `api_endpoints_json` text,
  `bootstrap_urls_json` text,
  `verify_path` varchar(255) NOT NULL DEFAULT '/index/dylib_verify/verify',
  `update_title` varchar(255) NOT NULL DEFAULT '发现游戏新版本',
  `update_message` varchar(1024) NOT NULL DEFAULT '当前版本：{current_version} ({current_build})\n最新版本：{latest_version} ({latest_build})',
  `update_primary_title` varchar(64) NOT NULL DEFAULT '前往更新',
  `update_secondary_title` varchar(64) NOT NULL DEFAULT '稍后提醒',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Signed Dylib runtime discovery/update configuration';

INSERT IGNORE INTO `fa_dylib_runtime_config`
(`id`,`config_version`,`api_endpoints_json`,`bootstrap_urls_json`,`verify_path`,`updated_at`)
VALUES (1,1,'[]','[]','/index/dylib_verify/verify',UNIX_TIMESTAMP());

CREATE TABLE IF NOT EXISTS `fa_dylib_runtime_notice` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `category_id` int unsigned NOT NULL DEFAULT '0',
  `min_access_level` varchar(24) NOT NULL DEFAULT '',
  `notice_key` varchar(128) NOT NULL,
  `revision` int unsigned NOT NULL DEFAULT '1',
  `priority` int NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL DEFAULT '',
  `message` text,
  `primary_title` varchar(64) NOT NULL DEFAULT '',
  `primary_action` varchar(32) NOT NULL DEFAULT 'dismiss',
  `primary_url` varchar(1024) NOT NULL DEFAULT '',
  `secondary_title` varchar(64) NOT NULL DEFAULT '',
  `secondary_action` varchar(32) NOT NULL DEFAULT 'dismiss',
  `secondary_url` varchar(1024) NOT NULL DEFAULT '',
  `starts_at` int unsigned NOT NULL DEFAULT '0',
  `ends_at` int unsigned NOT NULL DEFAULT '0',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notice_key` (`notice_key`),
  KEY `idx_active_scope` (`enabled`,`category_id`,`priority`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Server-controlled Dylib notices';
