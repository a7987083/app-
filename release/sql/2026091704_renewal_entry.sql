-- Phase 17.3 / 2026091704 renewal-only source entry.
-- Idempotent for repeated online-update execution on MySQL 5.7+.

SET @zonoe_has_renewal_entry := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fa_category'
      AND COLUMN_NAME = 'renewal_entry'
);

SET @zonoe_renewal_entry_sql := IF(
    @zonoe_has_renewal_entry = 0,
    'ALTER TABLE `fa_category` ADD COLUMN `renewal_entry` tinyint(1) unsigned NOT NULL DEFAULT ''0'' COMMENT ''续费入口:1是,0否'' AFTER `bt2b`',
    'SELECT 1'
);

PREPARE zonoe_renewal_entry_stmt FROM @zonoe_renewal_entry_sql;
EXECUTE zonoe_renewal_entry_stmt;
DEALLOCATE PREPARE zonoe_renewal_entry_stmt;

UPDATE `fa_category`
SET `renewal_entry` = 0
WHERE `renewal_entry` IS NULL OR `renewal_entry` NOT IN (0,1);
