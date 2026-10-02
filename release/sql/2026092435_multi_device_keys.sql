-- 2435: allow multiple active device public keys per UDID + Dylib.
-- MySQL 5.7 compatible and idempotent.
--
-- Authorization scope remains UDID + Dylib. Public keys are independent
-- enrolled credentials below that scope. Stale keys are removed after
-- 365 days without use by DylibDeviceAuthService.

SET @idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='fa_dylib_device_key'
    AND INDEX_NAME='uk_dylib_device'
);
SET @q := IF(
  @idx>0,
  'ALTER TABLE `fa_dylib_device_key` DROP INDEX `uk_dylib_device`',
  'SELECT 1'
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='fa_dylib_device_key'
    AND INDEX_NAME='uk_device_dylib_pubkey'
);
SET @q := IF(
  @idx=0,
  'ALTER TABLE `fa_dylib_device_key` ADD UNIQUE KEY `uk_device_dylib_pubkey` (`udid_hash`,`dylib_id`,`public_key_hash`)',
  'SELECT 1'
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='fa_dylib_device_key'
    AND INDEX_NAME='idx_key_last_used'
);
SET @q := IF(
  @idx=0,
  'ALTER TABLE `fa_dylib_device_key` ADD KEY `idx_key_last_used` (`last_used_at`)',
  'SELECT 1'
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
