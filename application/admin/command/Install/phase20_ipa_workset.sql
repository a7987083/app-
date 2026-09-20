-- Phase 20.8 active work-set + durable MD5 parse cache.
-- MySQL 5.7 compatible and idempotent.

SET @db := DATABASE();
SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='fa_ipa_metadata' AND column_name='referenced'),
  'SELECT 1',
  'ALTER TABLE `fa_ipa_metadata` ADD COLUMN `referenced` tinyint(1) unsigned NOT NULL DEFAULT ''1'' AFTER `parser_version`'
);
PREPARE phase20_stmt FROM @sql; EXECUTE phase20_stmt; DEALLOCATE PREPARE phase20_stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='fa_ipa_metadata' AND column_name='needs_reparse'),
  'SELECT 1',
  'ALTER TABLE `fa_ipa_metadata` ADD COLUMN `needs_reparse` tinyint(1) unsigned NOT NULL DEFAULT ''0'' AFTER `referenced`'
);
PREPARE phase20_stmt FROM @sql; EXECUTE phase20_stmt; DEALLOCATE PREPARE phase20_stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='fa_ipa_metadata' AND index_name='idx_reference_parse'),
  'SELECT 1',
  'ALTER TABLE `fa_ipa_metadata` ADD KEY `idx_reference_parse` (`referenced`,`parse_state`,`id`)'
);
PREPARE phase20_stmt FROM @sql; EXECUTE phase20_stmt; DEALLOCATE PREPARE phase20_stmt;

CREATE TABLE IF NOT EXISTS `fa_ipa_parse_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `md5` char(32) NOT NULL DEFAULT '',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT '0',
  `parser_version` int(10) unsigned NOT NULL DEFAULT '1',
  `bundle_id` varchar(255) NOT NULL DEFAULT '',
  `package_name` varchar(255) NOT NULL DEFAULT '',
  `package_version` varchar(100) NOT NULL DEFAULT '',
  `package_build` varchar(100) NOT NULL DEFAULT '',
  `minimum_ios` varchar(100) NOT NULL DEFAULT '',
  `executable` varchar(255) NOT NULL DEFAULT '',
  `payload_json` mediumtext,
  `parsed_at` int(10) unsigned NOT NULL DEFAULT '0',
  `last_used_at` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parse_fingerprint` (`md5`,`file_size`,`parser_version`),
  KEY `idx_last_used` (`last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 durable MD5 parse-result cache';

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='fa_ipa_parse_cache' AND column_name='payload_json'),
  'SELECT 1',
  'ALTER TABLE `fa_ipa_parse_cache` ADD COLUMN `payload_json` mediumtext AFTER `executable`'
);
PREPARE phase20_stmt FROM @sql; EXECUTE phase20_stmt; DEALLOCATE PREPARE phase20_stmt;
