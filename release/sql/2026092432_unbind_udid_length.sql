-- ZONOE 2026092432
-- Allow self-service transfer identifiers up to 128 characters without
-- changing existing data or narrowing wider production schemas.
-- MySQL 5.7 compatible and idempotent.

SET @zonoe_schema := DATABASE();
SET @zonoe_udid_len := (
    SELECT CHARACTER_MAXIMUM_LENGTH
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @zonoe_schema
       AND TABLE_NAME = 'fa_kami'
       AND COLUMN_NAME = 'udid'
     LIMIT 1
);
SET @zonoe_udid_nullable := (
    SELECT IS_NULLABLE
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @zonoe_schema
       AND TABLE_NAME = 'fa_kami'
       AND COLUMN_NAME = 'udid'
     LIMIT 1
);
SET @zonoe_udid_default := (
    SELECT COLUMN_DEFAULT
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @zonoe_schema
       AND TABLE_NAME = 'fa_kami'
       AND COLUMN_NAME = 'udid'
     LIMIT 1
);
SET @zonoe_udid_charset := (
    SELECT CHARACTER_SET_NAME
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @zonoe_schema
       AND TABLE_NAME = 'fa_kami'
       AND COLUMN_NAME = 'udid'
     LIMIT 1
);
SET @zonoe_udid_collation := (
    SELECT COLLATION_NAME
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @zonoe_schema
       AND TABLE_NAME = 'fa_kami'
       AND COLUMN_NAME = 'udid'
     LIMIT 1
);
SET @zonoe_udid_comment := (
    SELECT COLUMN_COMMENT
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @zonoe_schema
       AND TABLE_NAME = 'fa_kami'
       AND COLUMN_NAME = 'udid'
     LIMIT 1
);

SET @zonoe_udid_sql := IF(
    @zonoe_udid_len IS NOT NULL AND @zonoe_udid_len < 128,
    CONCAT(
        'ALTER TABLE `fa_kami` MODIFY COLUMN `udid` varchar(128)',
        IF(@zonoe_udid_charset IS NULL OR @zonoe_udid_charset = '', '', CONCAT(' CHARACTER SET ', @zonoe_udid_charset)),
        IF(@zonoe_udid_collation IS NULL OR @zonoe_udid_collation = '', '', CONCAT(' COLLATE ', @zonoe_udid_collation)),
        IF(@zonoe_udid_nullable = 'YES', ' NULL', ' NOT NULL'),
        IF(@zonoe_udid_default IS NULL, '', CONCAT(' DEFAULT ', QUOTE(@zonoe_udid_default))),
        IF(@zonoe_udid_comment IS NULL OR @zonoe_udid_comment = '', '', CONCAT(' COMMENT ', QUOTE(@zonoe_udid_comment)))
    ),
    'SELECT 1'
);
PREPARE zonoe_udid_stmt FROM @zonoe_udid_sql;
EXECUTE zonoe_udid_stmt;
DEALLOCATE PREPARE zonoe_udid_stmt;
