CREATE TABLE IF NOT EXISTS `fa_source_change` (
  `revision` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` int(11) unsigned NOT NULL DEFAULT '0',
  `action` varchar(16) NOT NULL DEFAULT 'update',
  `changed_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`revision`),
  KEY `idx_app_id` (`app_id`),
  KEY `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='软件源V3增量变更日志';
