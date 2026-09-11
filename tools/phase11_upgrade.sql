-- ZONOE Phase 11 in-place upgrade (idempotent for Phase 10 databases)
SET @db := DATABASE();
SET @has_transfer_count := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_kami' AND COLUMN_NAME='transfer_count');
SET @sql := IF(@has_transfer_count=0,
  "ALTER TABLE `fa_kami` ADD COLUMN `transfer_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '换绑次数' AFTER `kmyp`",
  "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `fa_card_transfer_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `kami_id` int(11) unsigned NOT NULL DEFAULT '0',
  `kami` varchar(128) NOT NULL DEFAULT '',
  `old_udid` varchar(128) NOT NULL DEFAULT '',
  `new_udid` varchar(128) NOT NULL DEFAULT '',
  `ip` varchar(64) NOT NULL DEFAULT '',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `message` varchar(255) NOT NULL DEFAULT '',
  `endtime` int(11) NOT NULL DEFAULT '0',
  `transfer_count` int(10) unsigned NOT NULL DEFAULT '0',
  `addtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), KEY `idx_old_udid` (`old_udid`), KEY `idx_new_udid` (`new_udid`),
  KEY `idx_kami` (`kami`), KEY `idx_ip_addtime` (`ip`,`addtime`), KEY `idx_status_addtime` (`status`,`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='设备换绑日志';

CREATE TABLE IF NOT EXISTS `fa_authorization_event` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `event` varchar(32) NOT NULL DEFAULT '', `kami_id` int(11) unsigned NOT NULL DEFAULT '0',
  `kami` varchar(128) NOT NULL DEFAULT '', `udid` varchar(128) NOT NULL DEFAULT '',
  `related_udid` varchar(128) NOT NULL DEFAULT '', `duration` int(11) NOT NULL DEFAULT '0',
  `endtime` int(11) NOT NULL DEFAULT '0', `ip` varchar(64) NOT NULL DEFAULT '',
  `detail` varchar(255) NOT NULL DEFAULT '', `addtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), KEY `idx_event_addtime` (`event`,`addtime`), KEY `idx_udid` (`udid`), KEY `idx_kami` (`kami`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='授权事件日志';

INSERT INTO `fa_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`)
SELECT 'unbind_max_count','basic','换绑总次数','每个连续有效授权最多自助换绑次数','number','3','','',''
WHERE NOT EXISTS (SELECT 1 FROM `fa_config` WHERE `name`='unbind_max_count');
INSERT INTO `fa_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`)
SELECT 'unbind_daily_limit','basic','每日换绑次数','同一授权链每天最多成功换绑次数','number','1','','',''
WHERE NOT EXISTS (SELECT 1 FROM `fa_config` WHERE `name`='unbind_daily_limit');
INSERT INTO `fa_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`)
SELECT 'unbind_cooldown_seconds','basic','换绑冷却秒数','成功换绑后再次换绑的最短间隔','number','3600','','',''
WHERE NOT EXISTS (SELECT 1 FROM `fa_config` WHERE `name`='unbind_cooldown_seconds');
INSERT INTO `fa_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`)
SELECT 'unbind_ip_hour_limit','basic','IP每小时尝试次数','同一IP每小时最多提交换绑请求次数','number','10','','',''
WHERE NOT EXISTS (SELECT 1 FROM `fa_config` WHERE `name`='unbind_ip_hour_limit');
