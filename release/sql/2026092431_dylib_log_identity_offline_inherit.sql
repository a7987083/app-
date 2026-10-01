-- 2431: Dylib verify audit identity + version offline inheritance.
-- MySQL 5.7 compatible and idempotent.

-- Keep hashes for internal correlation, but store the audit values admins actually need.
SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_dylib_verify_log' AND COLUMN_NAME='udid');
SET @q := IF(@c=0,'ALTER TABLE `fa_dylib_verify_log` ADD COLUMN `udid` varchar(255) NOT NULL DEFAULT '''' AFTER `id`','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_dylib_verify_log' AND COLUMN_NAME='ip');
SET @q := IF(@c=0,'ALTER TABLE `fa_dylib_verify_log` ADD COLUMN `ip` varchar(45) NOT NULL DEFAULT '''' AFTER `udid`','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @i := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_dylib_verify_log' AND INDEX_NAME='idx_verify_udid');
SET @q := IF(@i=0,'ALTER TABLE `fa_dylib_verify_log` ADD KEY `idx_verify_udid` (`udid`(64))','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @i := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_dylib_verify_log' AND INDEX_NAME='idx_verify_ip');
SET @q := IF(@i=0,'ALTER TABLE `fa_dylib_verify_log` ADD KEY `idx_verify_ip` (`ip`)','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- NULL means: inherit fa_dylib.default_offline_grace.
-- Existing explicit values are intentionally preserved; only newly saved blank values inherit.
SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_dylib_version' AND COLUMN_NAME='offline_grace');
SET @q := IF(@c>0,'ALTER TABLE `fa_dylib_version` MODIFY COLUMN `offline_grace` int unsigned NULL DEFAULT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
