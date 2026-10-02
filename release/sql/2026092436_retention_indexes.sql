-- 2436 retention hardening indexes.
-- MySQL 5.7 compatible and idempotent.

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='fa_ipa_scan_job'
    AND INDEX_NAME='idx_retention_status_updated'
);
SET @q := IF(
  @idx=0,
  'ALTER TABLE `fa_ipa_scan_job` ADD KEY `idx_retention_status_updated` (`status`,`updated_at`,`id`)',
  'SELECT 1'
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='fa_authorization_event'
    AND INDEX_NAME='idx_addtime'
);
SET @q := IF(
  @idx=0,
  'ALTER TABLE `fa_authorization_event` ADD KEY `idx_addtime` (`addtime`,`id`)',
  'SELECT 1'
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='fa_admin_log'
    AND INDEX_NAME='idx_createtime'
);
SET @q := IF(
  @idx=0,
  'ALTER TABLE `fa_admin_log` ADD KEY `idx_createtime` (`createtime`,`id`)',
  'SELECT 1'
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='fa_ipa_asset'
    AND INDEX_NAME='idx_asset_retention'
);
SET @q := IF(
  @idx=0,
  'ALTER TABLE `fa_ipa_asset` ADD KEY `idx_asset_retention` (`status`,`updated_at`,`id`)',
  'SELECT 1'
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
