-- IPA Data Center v1.2 worker heartbeat state
-- MySQL 5.7 compatible and idempotent.
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
