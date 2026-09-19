-- Phase 20 IPA software-source registry + durable OpenList config permissions.
-- MySQL 5.7 compatible and idempotent.

CREATE TABLE IF NOT EXISTS `fa_ipa_mysql_source` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `slug` varchar(64) NOT NULL DEFAULT '',
  `host` varchar(255) NOT NULL DEFAULT '127.0.0.1',
  `port` int(10) unsigned NOT NULL DEFAULT '3306',
  `database_name` varchar(128) NOT NULL DEFAULT '',
  `username` varchar(128) NOT NULL DEFAULT '',
  `password_ciphertext` text,
  `table_name` varchar(128) NOT NULL DEFAULT 'fa_category',
  `priority` int(11) NOT NULL DEFAULT '100',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `last_health` varchar(20) NOT NULL DEFAULT 'unknown',
  `last_error` varchar(500) NOT NULL DEFAULT '',
  `last_checked_at` int(10) unsigned NOT NULL DEFAULT '0',
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_enabled_priority` (`enabled`,`priority`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 external MySQL software sources';

SET @now := UNIX_TIMESTAMP();
SET @ipa_root := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);
SET @ipa_task := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/task' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_root,'ipa_center/mysql_source','MySQL 软件源','fa fa-database','','Phase20 software-source management',1,@now,@now,65,'normal'
WHERE @ipa_root IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/mysql_source');

SET @mysql_page := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/mysql_source' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@mysql_page,'ipa_center/mysql_source_list','软件源列表','fa fa-circle-o','','Read software-source configuration',0,@now,@now,0,'normal'
WHERE @mysql_page IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/mysql_source_list');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@mysql_page,'ipa_center/mysql_source_save','保存软件源','fa fa-circle-o','','Create/update software-source configuration',0,@now,@now,0,'normal'
WHERE @mysql_page IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/mysql_source_save');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@mysql_page,'ipa_center/mysql_source_test','测试软件源','fa fa-circle-o','','Test software-source connection',0,@now,@now,0,'normal'
WHERE @mysql_page IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/mysql_source_test');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@mysql_page,'ipa_center/mysql_source_delete','删除软件源','fa fa-circle-o','','Delete software-source configuration only',0,@now,@now,0,'normal'
WHERE @mysql_page IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/mysql_source_delete');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_task,'ipa_center/parse_one','解析 1 个 IPA','fa fa-circle-o','','Parse one eligible pending IPA',0,@now,@now,0,'normal'
WHERE @ipa_task IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/parse_one');
