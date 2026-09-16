-- Phase 16 / 2026091611 card permission scopes.
-- Idempotent for repeated online-update execution on MySQL 5.7+.

SET @zonoe_has_card_scope := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fa_kami'
      AND COLUMN_NAME = 'card_scope'
);

SET @zonoe_card_scope_sql := IF(
    @zonoe_has_card_scope = 0,
    'ALTER TABLE `fa_kami` ADD COLUMN `card_scope` tinyint(3) unsigned NOT NULL DEFAULT ''1'' COMMENT ''卡密用途:1全源,2仅验证,3指定App'' AFTER `transfer_count`',
    'SELECT 1'
);

PREPARE zonoe_card_scope_stmt FROM @zonoe_card_scope_sql;
EXECUTE zonoe_card_scope_stmt;
DEALLOCATE PREPARE zonoe_card_scope_stmt;

CREATE TABLE IF NOT EXISTS `fa_kami_app` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `kami_id` int(11) unsigned NOT NULL DEFAULT '0',
    `app_id` int(11) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_kami_app` (`kami_id`,`app_id`),
    KEY `idx_app_id` (`app_id`),
    KEY `idx_kami_id` (`kami_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='指定App卡授权映射';

UPDATE `fa_kami`
SET `card_scope` = 1
WHERE `card_scope` IS NULL OR `card_scope` NOT IN (1,2,3);
