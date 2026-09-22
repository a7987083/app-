-- ZONOE 2026092201 - IPA Data Center + Dylib Verification Center
-- MySQL 5.7 compatible, idempotent for upgrade from 2026091809.

CREATE TABLE IF NOT EXISTS `fa_ipa_source` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `base_url` varchar(512) NOT NULL,
  `root_path` varchar(1024) NOT NULL DEFAULT '/',
  `token_ciphertext` text,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `scan_page_size` int unsigned NOT NULL DEFAULT '500',
  `request_timeout` int unsigned NOT NULL DEFAULT '20',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), KEY `idx_enabled_id` (`enabled`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IPA OpenList data sources';

CREATE TABLE IF NOT EXISTS `fa_ipa_asset` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_id` int unsigned NOT NULL,
  `path_hash` char(64) NOT NULL,
  `path` varchar(2048) NOT NULL,
  `name` varchar(512) NOT NULL,
  `size_bytes` bigint unsigned NOT NULL DEFAULT '0',
  `modified_at` int unsigned NOT NULL DEFAULT '0',
  `raw_url` text,
  `etag` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(32) NOT NULL DEFAULT 'discovered',
  `bundle_id` varchar(255) NOT NULL DEFAULT '',
  `app_name` varchar(255) NOT NULL DEFAULT '',
  `app_version` varchar(128) NOT NULL DEFAULT '',
  `build_version` varchar(128) NOT NULL DEFAULT '',
  `minimum_os` varchar(64) NOT NULL DEFAULT '',
  `sha256` char(64) NOT NULL DEFAULT '',
  `last_error` text,
  `last_seen_at` int unsigned NOT NULL DEFAULT '0',
  `parsed_at` int unsigned NOT NULL DEFAULT '0',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_source_path_hash` (`source_id`,`path_hash`),
  KEY `idx_source_status_id` (`source_id`,`status`,`id`),
  KEY `idx_bundle_id` (`bundle_id`), KEY `idx_sha256` (`sha256`), KEY `idx_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Discovered IPA assets';

CREATE TABLE IF NOT EXISTS `fa_ipa_scan_job` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_id` int unsigned NOT NULL,
  `mode` varchar(24) NOT NULL DEFAULT 'incremental',
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `root_path` varchar(1024) NOT NULL DEFAULT '/',
  `discovered_count` int unsigned NOT NULL DEFAULT '0',
  `processed_count` int unsigned NOT NULL DEFAULT '0',
  `failed_count` int unsigned NOT NULL DEFAULT '0',
  `worker_id` varchar(128) NOT NULL DEFAULT '',
  `started_at` int unsigned NOT NULL DEFAULT '0',
  `finished_at` int unsigned NOT NULL DEFAULT '0',
  `last_error` text,
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), KEY `idx_status_id` (`status`,`id`), KEY `idx_source_created` (`source_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IPA scan jobs';

CREATE TABLE IF NOT EXISTS `fa_ipa_scan_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_id` bigint unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `item_type` varchar(16) NOT NULL DEFAULT 'directory',
  `path_hash` char(64) NOT NULL,
  `path` varchar(2048) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `retry_count` tinyint unsigned NOT NULL DEFAULT '0',
  `available_at` int unsigned NOT NULL DEFAULT '0',
  `locked_at` int unsigned NOT NULL DEFAULT '0',
  `worker_id` varchar(128) NOT NULL DEFAULT '',
  `last_error` text,
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_job_path_hash` (`job_id`,`path_hash`),
  KEY `idx_claim` (`status`,`available_at`,`id`), KEY `idx_job_status` (`job_id`,`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IPA scan queue items';

CREATE TABLE IF NOT EXISTS `fa_ipa_binary` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `relative_path` varchar(1024) NOT NULL,
  `binary_type` varchar(32) NOT NULL DEFAULT 'unknown',
  `name` varchar(255) NOT NULL DEFAULT '',
  `sha256` char(64) NOT NULL DEFAULT '',
  `size_bytes` bigint unsigned NOT NULL DEFAULT '0',
  `architectures` varchar(255) NOT NULL DEFAULT '',
  `install_name` varchar(512) NOT NULL DEFAULT '',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asset_path` (`asset_id`,`relative_path`(191)),
  KEY `idx_sha256` (`sha256`), KEY `idx_type_name` (`binary_type`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Mach-O/dylib/framework index';

CREATE TABLE IF NOT EXISTS `fa_ipa_category_binding` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `created_by` int unsigned NOT NULL DEFAULT '0',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_asset_category` (`asset_id`,`category_id`), KEY `idx_category` (`category_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Manual IPA to fa_category binding';

CREATE TABLE IF NOT EXISTS `fa_dylib` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dylib_key` varchar(128) NOT NULL,
  `name` varchar(255) NOT NULL,
  `verify_secret_ciphertext` text,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `default_offline_grace` int unsigned NOT NULL DEFAULT '900',
  `default_fail_action` varchar(32) NOT NULL DEFAULT 'disable_feature',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_dylib_key` (`dylib_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dylib registry';

SET @column_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_dylib' AND COLUMN_NAME='verify_secret_ciphertext');
SET @sql := IF(@column_exists=0, 'ALTER TABLE `fa_dylib` ADD COLUMN `verify_secret_ciphertext` text NULL AFTER `name`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `fa_dylib_version` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dylib_id` int unsigned NOT NULL,
  `version` varchar(64) NOT NULL,
  `build` varchar(64) NOT NULL DEFAULT '',
  `sha256` char(64) NOT NULL DEFAULT '',
  `file_size` bigint unsigned NOT NULL DEFAULT '0',
  `state` varchar(24) NOT NULL DEFAULT 'testing',
  `offline_grace` int unsigned NOT NULL DEFAULT '900',
  `fail_action` varchar(32) NOT NULL DEFAULT 'disable_feature',
  `notice` varchar(1024) NOT NULL DEFAULT '',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_dylib_version_build` (`dylib_id`,`version`,`build`), KEY `idx_dylib_state` (`dylib_id`,`state`,`id`), KEY `idx_sha256` (`sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dylib versions and policy';

CREATE TABLE IF NOT EXISTS `fa_dylib_app_binding` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dylib_id` int unsigned NOT NULL,
  `bundle_id` varchar(255) NOT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `fail_action_override` varchar(32) NOT NULL DEFAULT '',
  `offline_grace_override` int unsigned NOT NULL DEFAULT '0',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_dylib_bundle` (`dylib_id`,`bundle_id`), KEY `idx_bundle_enabled` (`bundle_id`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dylib may be bound to many games';

CREATE TABLE IF NOT EXISTS `fa_dylib_device_session` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_hash` char(64) NOT NULL,
  `udid_hash` char(64) NOT NULL,
  `dylib_version_id` bigint unsigned NOT NULL,
  `bundle_id` varchar(255) NOT NULL,
  `issued_at` int unsigned NOT NULL,
  `expires_at` int unsigned NOT NULL,
  `last_seen_at` int unsigned NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_session_hash` (`session_hash`), KEY `idx_udid_last_seen` (`udid_hash`,`last_seen_at`), KEY `idx_expire_status` (`expires_at`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Short-lived dylib verification sessions';

CREATE TABLE IF NOT EXISTS `fa_dylib_nonce` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nonce_hash` char(64) NOT NULL,
  `expires_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_nonce_hash` (`nonce_hash`), KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Replay protection nonces';

CREATE TABLE IF NOT EXISTS `fa_dylib_verify_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `udid_hash` char(64) NOT NULL,
  `bundle_id` varchar(255) NOT NULL DEFAULT '',
  `dylib_key` varchar(128) NOT NULL DEFAULT '',
  `dylib_version` varchar(64) NOT NULL DEFAULT '',
  `result_code` varchar(48) NOT NULL,
  `action` varchar(32) NOT NULL DEFAULT '',
  `ip_hash` char(64) NOT NULL DEFAULT '',
  `latency_ms` int unsigned NOT NULL DEFAULT '0',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), KEY `idx_created` (`created_at`), KEY `idx_udid_created` (`udid_hash`,`created_at`), KEY `idx_dylib_created` (`dylib_key`,`created_at`), KEY `idx_result_created` (`result_code`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dylib verification audit log';

SET @now := UNIX_TIMESTAMP();
INSERT IGNORE INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
VALUES ('file',0,'ipa_center','IPA 数据中心','fa fa-archive','','IPA / Dylib 数据与验证中心',1,@now,@now,120,'normal');
SET @ipa_pid := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);
INSERT IGNORE INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`) VALUES
('file',@ipa_pid,'ipa_center/index','总览','fa fa-dashboard','','',1,@now,@now,100,'normal'),
('file',@ipa_pid,'dylib_center/index','Dylib 验证中心','fa fa-shield','','',1,@now,@now,95,'normal'),
('file',@ipa_pid,'ipa_center/assets','IPA 资产','fa fa-file-archive-o','','',0,@now,@now,90,'normal'),
('file',@ipa_pid,'ipa_center/jobs','扫描任务','fa fa-tasks','','',0,@now,@now,80,'normal'),
('file',@ipa_pid,'ipa_center/categorySearch','搜索项目','fa fa-search','','',0,@now,@now,75,'normal'),
('file',@ipa_pid,'ipa_center/writebackPreview','写回预览','fa fa-eye','','',0,@now,@now,74,'normal'),
('file',@ipa_pid,'ipa_center/writebackApply','手动写回项目','fa fa-exchange','','',0,@now,@now,73,'normal'),
('file',@ipa_pid,'ipa_center/saveSource','保存 OpenList 数据源','fa fa-database','','',0,@now,@now,70,'normal'),
('file',@ipa_pid,'ipa_center/startScan','启动扫描','fa fa-play','','',0,@now,@now,60,'normal'),
('file',@ipa_pid,'dylib_center/versions','Dylib 版本列表','fa fa-code-fork','','',0,@now,@now,55,'normal'),
('file',@ipa_pid,'dylib_center/bindings','Dylib 游戏绑定','fa fa-link','','',0,@now,@now,54,'normal'),
('file',@ipa_pid,'dylib_center/logs','Dylib 验证日志','fa fa-list-alt','','',0,@now,@now,53,'normal'),
('file',@ipa_pid,'dylib_center/generateVerifySecret','生成 Dylib 验证密钥','fa fa-key','','',0,@now,@now,52,'normal'),
('file',@ipa_pid,'dylib_center/saveDylib','保存 Dylib','fa fa-save','','',0,@now,@now,51,'normal'),
('file',@ipa_pid,'dylib_center/saveVersion','保存 Dylib 版本','fa fa-save','','',0,@now,@now,50,'normal'),
('file',@ipa_pid,'dylib_center/saveBinding','保存 Dylib 绑定','fa fa-save','','',0,@now,@now,49,'normal');
