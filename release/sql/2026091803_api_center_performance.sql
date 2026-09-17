-- Phase 19.3 / 2026091803
-- Project API center + hot-path indexes for Legacy /appstore.
-- MySQL 5.7 compatible and idempotent.

CREATE TABLE IF NOT EXISTS `fa_api_endpoint` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint_key` varchar(64) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  `path` varchar(191) NOT NULL DEFAULT '',
  `method` varchar(16) NOT NULL DEFAULT 'GET',
  `source` varchar(20) NOT NULL DEFAULT 'system',
  `auth` varchar(64) NOT NULL DEFAULT '',
  `handler_key` varchar(64) NOT NULL DEFAULT '',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `description` varchar(255) NOT NULL DEFAULT '',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_endpoint_key` (`endpoint_key`),
  UNIQUE KEY `uniq_path` (`path`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='项目API注册表';

CREATE TABLE IF NOT EXISTS `fa_api_request_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint_key` varchar(64) NOT NULL DEFAULT '',
  `method` varchar(16) NOT NULL DEFAULT '',
  `path` varchar(255) NOT NULL DEFAULT '',
  `ip` varchar(64) NOT NULL DEFAULT '',
  `status_code` int(10) unsigned NOT NULL DEFAULT '200',
  `duration_ms` int(10) unsigned NOT NULL DEFAULT '0',
  `addtime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_endpoint_time` (`endpoint_key`,`addtime`),
  KEY `idx_addtime` (`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='项目API请求日志';

INSERT IGNORE INTO `fa_api_endpoint`
(`endpoint_key`,`name`,`path`,`method`,`source`,`auth`,`handler_key`,`enabled`,`description`,`createtime`,`updatetime`)
VALUES
('appstore','软件源接口','/appstore','ANY','system','UDID/卡密','appstore',1,'添加、刷新软件源与卡密激活',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('dylib_config','远程Dylib配置','/index/index/dylib','GET','system','UDID','dylib_config',1,'远程Dylib配置与授权状态',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('dylib_auth','Dylib授权','/index/index/apiface','GET','system','UDID+HMAC','dylib_auth',1,'动态库授权验证',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('unbind','换绑接口','/unbind','GET,POST','system','卡密+UDID','unbind',1,'设备换绑页面与提交',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('unbind_query','换绑查询','/unbind/query','GET','system','UDID','unbind_query',1,'查询换绑状态',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('license','授权查询','/license','GET,POST','system','卡密+UDID','license',1,'查询授权信息',UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- fa_kami: hot path /appstore queries by UDID and active entitlement.
SET @has_idx := (
  SELECT COUNT(*) FROM (
    SELECT INDEX_NAME,
           GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kami'
    GROUP BY INDEX_NAME
  ) s WHERE s.cols = 'udid,jh,endtime,id'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `fa_kami` ADD INDEX `idx_zonoe_udid_auth` (`udid`,`jh`,`endtime`,`id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- One-time card activation lookup.
SET @has_idx := (
  SELECT COUNT(*) FROM (
    SELECT INDEX_NAME,
           GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kami'
    GROUP BY INDEX_NAME
  ) s WHERE s.cols = 'kami,id'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `fa_kami` ADD INDEX `idx_zonoe_kami_lookup` (`kami`,`id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Blacklist lookup by UDID.
SET @has_idx := (
  SELECT COUNT(*) FROM (
    SELECT INDEX_NAME,
           GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_black'
    GROUP BY INDEX_NAME
  ) s WHERE s.cols = 'udid,id'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `fa_black` ADD INDEX `idx_zonoe_black_udid` (`udid`,`id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- App entitlement mapping.
SET @has_idx := (
  SELECT COUNT(*) FROM (
    SELECT INDEX_NAME,
           GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kami_app'
    GROUP BY INDEX_NAME
  ) s WHERE s.cols = 'kami_id,app_id'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `fa_kami_app` ADD INDEX `idx_zonoe_kami_app` (`kami_id`,`app_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
