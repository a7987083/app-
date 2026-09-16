-- 2026091705 hotfix: repair renewal_entry on installations where the 1704
-- migration did not persist. Safe for repeated execution on MySQL 5.7+.

SET @zonoe_has_renewal_entry_1705 := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fa_category'
      AND COLUMN_NAME = 'renewal_entry'
);

SET @zonoe_renewal_entry_sql_1705 := IF(
    @zonoe_has_renewal_entry_1705 = 0,
    'ALTER TABLE `fa_category` ADD COLUMN `renewal_entry` tinyint(1) unsigned NOT NULL DEFAULT ''0'' COMMENT ''续费入口:1是,0否''',
    'SELECT 1'
);

PREPARE zonoe_renewal_entry_stmt_1705 FROM @zonoe_renewal_entry_sql_1705;
EXECUTE zonoe_renewal_entry_stmt_1705;
DEALLOCATE PREPARE zonoe_renewal_entry_stmt_1705;

UPDATE `fa_category`
SET `renewal_entry` = 0
WHERE `renewal_entry` IS NULL OR `renewal_entry` NOT IN (0,1);
