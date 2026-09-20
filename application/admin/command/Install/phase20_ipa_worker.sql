-- Phase 20.9 persistent IPA worker queue.
-- MySQL 5.7 compatible and idempotent.

CREATE TABLE IF NOT EXISTS `fa_ipa_worker_job` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_key` varchar(100) NOT NULL DEFAULT '',
  `job_type` varchar(20) NOT NULL DEFAULT '',
  `ref_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `payload_json` text,
  `state` varchar(20) NOT NULL DEFAULT 'queued',
  `worker_token` varchar(64) NOT NULL DEFAULT '',
  `claimed_at` int(10) unsigned NOT NULL DEFAULT '0',
  `heartbeat_at` int(10) unsigned NOT NULL DEFAULT '0',
  `error_code` varchar(100) NOT NULL DEFAULT '',
  `error_message` text,
  `created_by` bigint(20) unsigned NOT NULL DEFAULT '0',
  `started_at` int(10) unsigned NOT NULL DEFAULT '0',
  `finished_at` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_job_key` (`job_key`),
  KEY `idx_state_id` (`state`,`id`),
  KEY `idx_type_state_id` (`job_type`,`state`,`id`),
  KEY `idx_worker_heartbeat` (`worker_token`,`heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 persistent IPA background worker jobs';

CREATE TABLE IF NOT EXISTS `fa_ipa_worker_state` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `worker_name` varchar(64) NOT NULL DEFAULT '',
  `worker_token` varchar(64) NOT NULL DEFAULT '',
  `pid` int(10) unsigned NOT NULL DEFAULT '0',
  `state` varchar(20) NOT NULL DEFAULT 'offline',
  `current_job_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `current_job_type` varchar(20) NOT NULL DEFAULT '',
  `started_at` int(10) unsigned NOT NULL DEFAULT '0',
  `heartbeat_at` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_worker_name` (`worker_name`),
  KEY `idx_heartbeat` (`heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Phase20 persistent IPA worker heartbeat';
