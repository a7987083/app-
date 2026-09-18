-- Phase 20.1 persistence/task/audit foundation.
-- MySQL 5.7 compatible. No foreign keys: deployment/updater order remains reversible.

CREATE TABLE IF NOT EXISTS `fa_ipa_metadata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_key` varchar(64) NOT NULL DEFAULT 'openlist',
  `remote_path` varchar(1024) NOT NULL DEFAULT '',
  `remote_path_hash` char(64) NOT NULL DEFAULT '',
  `file_name` varchar(255) NOT NULL DEFAULT '',
  `public_url` text,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT '0',
  `remote_mtime` bigint(20) unsigned NOT NULL DEFAULT '0',
  `etag` varchar(255) NOT NULL DEFAULT '',
  `md5` char(32) NOT NULL DEFAULT '',
  `sha256` char(64) NOT NULL DEFAULT '',
  `bundle_id` varchar(255) NOT NULL DEFAULT '',
  `package_name` varchar(255) NOT NULL DEFAULT '',
  `package_version` varchar(100) NOT NULL DEFAULT '',
  `package_build` varchar(100) NOT NULL DEFAULT '',
  `minimum_ios` varchar(100) NOT NULL DEFAULT '',
  `executable` varchar(255) NOT NULL DEFAULT '',
  `parse_state` varchar(20) NOT NULL DEFAULT 'pending',
  `parser_version` int(10) unsigned NOT NULL DEFAULT '1',
  `confidence_json` longtext,
  `raw_metadata_json` longtext,
  `normalized_metadata_json` longtext,
  `parse_error` text,
  `parsed_at` int(10) unsigned NOT NULL DEFAULT '0',
  `last_seen_at` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_source_remote_hash` (`source_key`,`remote_path_hash`),
  KEY `idx_md5_size` (`md5`,`file_size`),
  KEY `idx_bundle_id` (`bundle_id`(191)),
  KEY `idx_parse_state` (`parse_state`),
  KEY `idx_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 IPA normalized/raw metadata';

CREATE TABLE IF NOT EXISTS `fa_ipa_binding` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL DEFAULT '0',
  `metadata_id` int(10) unsigned NOT NULL DEFAULT '0',
  `source_key` varchar(64) NOT NULL DEFAULT 'openlist',
  `remote_path` varchar(1024) NOT NULL DEFAULT '',
  `remote_path_hash` char(64) NOT NULL DEFAULT '',
  `bundle_id` varchar(255) NOT NULL DEFAULT '',
  `md5` char(32) NOT NULL DEFAULT '',
  `bind_method` varchar(30) NOT NULL DEFAULT 'manual',
  `confidence` varchar(20) NOT NULL DEFAULT 'exact',
  `bound_by` int(10) unsigned NOT NULL DEFAULT '0',
  `first_bound_at` int(10) unsigned NOT NULL DEFAULT '0',
  `last_seen_at` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_category` (`category_id`),
  UNIQUE KEY `uniq_metadata` (`metadata_id`),
  UNIQUE KEY `uniq_source_remote_hash` (`source_key`,`remote_path_hash`),
  KEY `idx_bundle_id` (`bundle_id`(191)),
  KEY `idx_md5` (`md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 stable category to IPA binding';

CREATE TABLE IF NOT EXISTS `fa_ipa_scan_task` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_key` char(64) NOT NULL DEFAULT '',
  `trigger_type` varchar(20) NOT NULL DEFAULT 'manual',
  `source_key` varchar(64) NOT NULL DEFAULT 'openlist',
  `state` varchar(20) NOT NULL DEFAULT 'queued',
  `stage` varchar(40) NOT NULL DEFAULT 'queued',
  `cursor_json` longtext,
  `progress_current` int(10) unsigned NOT NULL DEFAULT '0',
  `progress_total` int(10) unsigned NOT NULL DEFAULT '0',
  `retry_count` int(10) unsigned NOT NULL DEFAULT '0',
  `error_code` varchar(100) NOT NULL DEFAULT '',
  `error_message` text,
  `started_at` int(10) unsigned NOT NULL DEFAULT '0',
  `heartbeat_at` int(10) unsigned NOT NULL DEFAULT '0',
  `finished_at` int(10) unsigned NOT NULL DEFAULT '0',
  `created_by` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_task_key` (`task_key`),
  KEY `idx_state_heartbeat` (`state`,`heartbeat_at`),
  KEY `idx_source_created` (`source_key`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 scan/background task';

CREATE TABLE IF NOT EXISTS `fa_ipa_scan_task_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `metadata_id` int(10) unsigned NOT NULL DEFAULT '0',
  `item_key` char(64) NOT NULL DEFAULT '',
  `state` varchar(20) NOT NULL DEFAULT 'queued',
  `stage` varchar(40) NOT NULL DEFAULT 'queued',
  `retry_after` int(10) unsigned NOT NULL DEFAULT '0',
  `retry_count` int(10) unsigned NOT NULL DEFAULT '0',
  `error_code` varchar(100) NOT NULL DEFAULT '',
  `error_message` text,
  `result_json` longtext,
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_task_item` (`task_id`,`item_key`),
  KEY `idx_task_state` (`task_id`,`state`),
  KEY `idx_retry_after` (`state`,`retry_after`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 task item/checkpoint';

CREATE TABLE IF NOT EXISTS `fa_ipa_governance_issue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `issue_key` char(64) NOT NULL DEFAULT '',
  `category_id` int(10) unsigned NOT NULL DEFAULT '0',
  `metadata_id` int(10) unsigned NOT NULL DEFAULT '0',
  `issue_type` varchar(40) NOT NULL DEFAULT '',
  `field_name` varchar(100) NOT NULL DEFAULT '',
  `db_value` text,
  `actual_value` text,
  `reason` text,
  `confidence` varchar(20) NOT NULL DEFAULT 'exact',
  `state` varchar(20) NOT NULL DEFAULT 'open',
  `ignore_until` int(10) unsigned NOT NULL DEFAULT '0',
  `plan_hash` char(64) NOT NULL DEFAULT '',
  `last_verified_at` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_issue_key` (`issue_key`),
  KEY `idx_type_state` (`issue_type`,`state`),
  KEY `idx_category_state` (`category_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 governance anomaly';

CREATE TABLE IF NOT EXISTS `fa_ipa_operation_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `operation_id` char(36) NOT NULL DEFAULT '',
  `idempotency_key` char(64) NOT NULL DEFAULT '',
  `operation_type` varchar(60) NOT NULL DEFAULT '',
  `category_id` int(10) unsigned NOT NULL DEFAULT '0',
  `metadata_id` int(10) unsigned NOT NULL DEFAULT '0',
  `plan_hash` char(64) NOT NULL DEFAULT '',
  `state` varchar(20) NOT NULL DEFAULT 'queued',
  `before_json` longtext,
  `after_json` longtext,
  `result_json` longtext,
  `error_message` text,
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0',
  `started_at` int(10) unsigned NOT NULL DEFAULT '0',
  `finished_at` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_operation_id` (`operation_id`),
  UNIQUE KEY `uniq_idempotency` (`idempotency_key`),
  KEY `idx_category_created` (`category_id`,`createtime`),
  KEY `idx_type_state` (`operation_type`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 mutating-operation audit/idempotency log';

CREATE TABLE IF NOT EXISTS `fa_ipa_writeback_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_key` varchar(64) NOT NULL DEFAULT 'default',
  `template_version` int(10) unsigned NOT NULL DEFAULT '1',
  `rule_key` varchar(80) NOT NULL DEFAULT '',
  `source_field` varchar(100) NOT NULL DEFAULT '',
  `target_field` varchar(100) NOT NULL DEFAULT '',
  `strategy` varchar(30) NOT NULL DEFAULT 'preview',
  `min_confidence` varchar(20) NOT NULL DEFAULT 'exact',
  `options_json` longtext,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_template_rule` (`template_key`,`template_version`,`rule_key`),
  KEY `idx_template_enabled` (`template_key`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 write-back template rules';

-- High-risk permissions are deliberately hidden menu nodes and remain separate.
SET @now := UNIX_TIMESTAMP();
SET @ipa_root := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);
SET @ipa_binding := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/binding' LIMIT 1);
SET @ipa_gov := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/governance' LIMIT 1);
SET @ipa_writeback := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/writeback' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_binding,'ipa_center/binding_mutate','修改 IPA 绑定','fa fa-circle-o','','Phase20 high-risk permission',0,@now,@now,0,'normal' WHERE @ipa_binding IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/binding_mutate');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_gov,'ipa_center/governance_apply','执行治理修复','fa fa-circle-o','','Phase20 high-risk permission',0,@now,@now,0,'normal' WHERE @ipa_gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_apply');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_gov,'ipa_center/openlist_mutate','修改 OpenList 文件','fa fa-circle-o','','Phase20 high-risk permission',0,@now,@now,0,'normal' WHERE @ipa_gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/openlist_mutate');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_writeback,'ipa_center/writeback_apply','执行数据库写回','fa fa-circle-o','','Phase20 high-risk permission',0,@now,@now,0,'normal' WHERE @ipa_writeback IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/writeback_apply');
