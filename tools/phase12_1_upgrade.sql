-- ZONOE Phase 12.1 transfer quota semantic migration.
-- Phase 11/12 stored fa_kami.transfer_count as USED count (default 0).
-- Phase 12.1 stores REMAINING count (default 100). The column default is the
-- idempotency marker, so this conversion executes only once.
SET @db := DATABASE();
SET @transfer_default := (
  SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_kami' AND COLUMN_NAME='transfer_count'
  LIMIT 1
);
SET @needs_transfer_quota_migration := IF(@transfer_default IS NOT NULL AND CAST(@transfer_default AS UNSIGNED)=0, 1, 0);
SET @sql := IF(@needs_transfer_quota_migration=1,
  "UPDATE `fa_kami` SET `transfer_count`=GREATEST(0,100-`transfer_count`)",
  "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@needs_transfer_quota_migration=1,
  "ALTER TABLE `fa_kami` MODIFY COLUMN `transfer_count` int(10) unsigned NOT NULL DEFAULT '100' COMMENT '剩余换绑次数'",
  "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `fa_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`)
SELECT 'unbind_max_count','basic','新卡默认换绑次数','新生成卡密默认可换绑次数','number','100','','',''
WHERE NOT EXISTS (SELECT 1 FROM `fa_config` WHERE `name`='unbind_max_count');
UPDATE `fa_config`
SET `title`='新卡默认换绑次数', `tip`='新生成卡密默认可换绑次数',
    `value`=IF(`value`='3','100',`value`)
WHERE `name`='unbind_max_count';
