-- ZONOE 2026092202 - IPA management / worker visibility hotfix
-- MySQL 5.7 compatible and idempotent. Upgrade target: source-v2026092201.

CREATE TABLE IF NOT EXISTS `fa_ipa_worker_state` (
  `worker_type` varchar(32) NOT NULL,
  `worker_id` varchar(128) NOT NULL DEFAULT '',
  `status` varchar(24) NOT NULL DEFAULT 'idle',
  `current_item_id` bigint unsigned NOT NULL DEFAULT '0',
  `heartbeat_at` int unsigned NOT NULL DEFAULT '0',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`worker_type`),
  KEY `idx_heartbeat` (`heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IPA worker heartbeat state';

SET @now := UNIX_TIMESTAMP();
SET @ipa_pid := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
VALUES
('file',@ipa_pid,'ipa_center/deleteSource','删除 OpenList 数据源','fa fa-trash','','',0,@now,@now,69,'normal'),
('file',@ipa_pid,'ipa_center/retryParse','重新解析 IPA','fa fa-repeat','','',0,@now,@now,68,'normal');
