-- ZONOE 2026092203 - IPA ops controls, software sources and comparison
-- MySQL 5.7 compatible and idempotent. Upgrade target: source-v2026092202.

CREATE TABLE IF NOT EXISTS `fa_ipa_setting` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` varchar(1024) NOT NULL DEFAULT '',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IPA operations settings';

INSERT IGNORE INTO `fa_ipa_setting` (`setting_key`,`setting_value`,`updated_at`) VALUES
('parse_enabled','1',UNIX_TIMESTAMP()),
('parse_window_minutes','5',UNIX_TIMESTAMP()),
('parse_window_limit','3',UNIX_TIMESTAMP()),
('parse_hour_limit','24',UNIX_TIMESTAMP()),
('parse_day_limit','150',UNIX_TIMESTAMP()),
('parse_retry_minutes','30',UNIX_TIMESTAMP()),
('worker_alive_seconds','180',UNIX_TIMESTAMP());

CREATE TABLE IF NOT EXISTS `fa_ipa_parse_attempt` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL DEFAULT '0',
  `worker_id` varchar(128) NOT NULL DEFAULT '',
  `result` varchar(24) NOT NULL DEFAULT '',
  `error` varchar(2000) NOT NULL DEFAULT '',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_asset_created` (`asset_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IPA parse rate-limit and audit attempts';

CREATE TABLE IF NOT EXISTS `fa_ipa_software_source` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `slug` varchar(128) NOT NULL,
  `host` varchar(255) NOT NULL DEFAULT '127.0.0.1',
  `port` int unsigned NOT NULL DEFAULT '3306',
  `database_name` varchar(128) NOT NULL,
  `username` varchar(128) NOT NULL,
  `password_ciphertext` text,
  `table_name` varchar(128) NOT NULL DEFAULT 'fa_category',
  `priority` int NOT NULL DEFAULT '100',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `allow_write` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_enabled_priority` (`enabled`,`priority`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External MySQL software sources for IPA comparison';

CREATE TABLE IF NOT EXISTS `fa_ipa_compare_result` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `software_source_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL DEFAULT '0',
  `match_type` varchar(32) NOT NULL DEFAULT 'none',
  `match_score` int NOT NULL DEFAULT '0',
  `status` varchar(32) NOT NULL DEFAULT 'unmatched',
  `anomaly_count` int unsigned NOT NULL DEFAULT '0',
  `diff_json` mediumtext,
  `compared_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asset_source_category` (`asset_id`,`software_source_id`,`category_id`),
  KEY `idx_asset_status` (`asset_id`,`status`,`id`),
  KEY `idx_source_category` (`software_source_id`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IPA vs software-source comparison results';

SET @now := UNIX_TIMESTAMP();
SET @ipa_pid := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);
INSERT IGNORE INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`) VALUES
('file',@ipa_pid,'ipa_source_center/index','软件源','fa fa-database','','多 MySQL 软件源管理',1,@now,@now,98,'normal'),
('file',@ipa_pid,'ipa_source_center/listSources','软件源列表','fa fa-list','','',0,@now,@now,97,'normal'),
('file',@ipa_pid,'ipa_source_center/saveSource','保存软件源','fa fa-save','','',0,@now,@now,96,'normal'),
('file',@ipa_pid,'ipa_source_center/testSource','测试软件源','fa fa-plug','','',0,@now,@now,95,'normal'),
('file',@ipa_pid,'ipa_source_center/deleteSource','删除软件源','fa fa-trash','','',0,@now,@now,94,'normal'),
('file',@ipa_pid,'ipa_center/saveParseSettings','保存解析设置','fa fa-sliders','','',0,@now,@now,67,'normal'),
('file',@ipa_pid,'ipa_center/assetDetail','IPA/异常详情','fa fa-eye','','',0,@now,@now,66,'normal'),
('file',@ipa_pid,'ipa_center/compareAsset','重新比对 IPA','fa fa-exchange','','',0,@now,@now,65,'normal'),
('file',@ipa_pid,'ipa_center/applyCompareWriteback','异常写回数据库','fa fa-database','','',0,@now,@now,64,'normal'),
('file',@ipa_pid,'ipa_center/forceDeleteSource','强制停止并删除 OpenList 源','fa fa-trash-o','','',0,@now,@now,63,'normal');
