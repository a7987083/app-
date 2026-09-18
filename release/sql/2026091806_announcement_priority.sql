-- Phase 19.4.1: migrate legacy announcement expiry placeholders to
-- current-authorization placeholders. Idempotent because replacements disappear
-- after the first successful execution.
UPDATE `fa_config`
SET `value` = REPLACE(
    REPLACE(`value`, '[全源到期时间]', '[到期时间]'),
    '[全源剩余时间]', '[剩余时间]'
)
WHERE `name` = 'message'
  AND (
      `value` LIKE '%[全源到期时间]%'
      OR `value` LIKE '%[全源剩余时间]%'
  );
