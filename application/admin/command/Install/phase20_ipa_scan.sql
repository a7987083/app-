-- Phase 20.2 OpenList source + discovery permissions.
-- MySQL 5.7 compatible.

CREATE TABLE IF NOT EXISTS `fa_ipa_source` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_key` varchar(64) NOT NULL DEFAULT 'openlist',
  `source_type` varchar(30) NOT NULL DEFAULT 'openlist',
  `name` varchar(100) NOT NULL DEFAULT 'OpenList',
  `base_url` varchar(500) NOT NULL DEFAULT '',
  `api_base` varchar(100) NOT NULL DEFAULT '/api',
  `scan_path` varchar(1024) NOT NULL DEFAULT '/',
  `public_url_template` varchar(1000) NOT NULL DEFAULT '',
  `token_ciphertext` text,
  `token_hint` varchar(32) NOT NULL DEFAULT '',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `schedule_enabled` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `interval_minutes` int(10) unsigned NOT NULL DEFAULT '10',
  `batch_size` int(10) unsigned NOT NULL DEFAULT '20',
  `cache_ttl` int(10) unsigned NOT NULL DEFAULT '1800',
  `request_timeout` int(10) unsigned NOT NULL DEFAULT '15',
  `request_retries` int(10) unsigned NOT NULL DEFAULT '2',
  `last_health` varchar(20) NOT NULL DEFAULT 'unknown',
  `last_checked_at` int(10) unsigned NOT NULL DEFAULT '0',
  `last_scan_at` int(10) unsigned NOT NULL DEFAULT '0',
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_source_key` (`source_key`),
  KEY `idx_enabled_schedule` (`enabled`,`schedule_enabled`),
  KEY `idx_last_scan` (`last_scan_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 IPA network source';

SET @now := UNIX_TIMESTAMP();
SET @ipa_task := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/task' LIMIT 1);
SET @ipa_setting := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/setting' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_task,'ipa_center/task_list','扫描任务列表','fa fa-circle-o','','Phase20 read permission',0,@now,@now,0,'normal'
WHERE @ipa_task IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/task_list');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_task,'ipa_center/scan_start','启动 IPA 扫描','fa fa-circle-o','','Phase20 scan permission',0,@now,@now,0,'normal'
WHERE @ipa_task IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/scan_start');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_setting,'ipa_center/source_save','保存 IPA 网络源','fa fa-circle-o','','Phase20 source permission',0,@now,@now,0,'normal'
WHERE @ipa_setting IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/source_save');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_setting,'ipa_center/source_test','测试 IPA 网络源','fa fa-circle-o','','Phase20 source permission',0,@now,@now,0,'normal'
WHERE @ipa_setting IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/source_test');
