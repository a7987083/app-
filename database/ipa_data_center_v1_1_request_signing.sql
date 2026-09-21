-- IPA Data Center v1.1 request signing migration
-- MySQL 5.7 compatible and safe to apply once after v1 schema.

SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'fa_dylib'
    AND COLUMN_NAME = 'verify_secret_ciphertext'
);

SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `fa_dylib` ADD COLUMN `verify_secret_ciphertext` text NULL AFTER `name`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
