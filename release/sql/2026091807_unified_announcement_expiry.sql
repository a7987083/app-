-- Phase 19.4.2: collapse all announcement expiry placeholders into one clock.
-- Safe to run repeatedly: once a legacy token is replaced, subsequent runs are no-ops.

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[全源到期时间|', '[到期时间|')
WHERE `name` = 'message' AND `value` LIKE '%[全源到期时间|%';

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[全源到期时间]', '[到期时间]')
WHERE `name` = 'message' AND `value` LIKE '%[全源到期时间]%';

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[部分到期时间|', '[到期时间|')
WHERE `name` = 'message' AND `value` LIKE '%[部分到期时间|%';

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[部分到期时间]', '[到期时间]')
WHERE `name` = 'message' AND `value` LIKE '%[部分到期时间]%';

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[验证到期时间|', '[到期时间|')
WHERE `name` = 'message' AND `value` LIKE '%[验证到期时间|%';

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[验证到期时间]', '[到期时间]')
WHERE `name` = 'message' AND `value` LIKE '%[验证到期时间]%';

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[全源剩余时间|', '[剩余时间|')
WHERE `name` = 'message' AND `value` LIKE '%[全源剩余时间|%';

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[全源剩余时间]', '[剩余时间]')
WHERE `name` = 'message' AND `value` LIKE '%[全源剩余时间]%';

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[部分剩余时间|', '[剩余时间|')
WHERE `name` = 'message' AND `value` LIKE '%[部分剩余时间|%';

UPDATE `fa_config` SET `value` = REPLACE(`value`, '[部分剩余时间]', '[剩余时间]')
WHERE `name` = 'message' AND `value` LIKE '%[部分剩余时间]%';
