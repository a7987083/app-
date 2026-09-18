-- Phase 19.4.x closeout / 2026091808
-- MySQL 5.7 compatible and safe to run repeatedly.

-- System API metadata: authorization query uses the safe /authorization route.
UPDATE `fa_api_endpoint`
SET `name`='授权查询',
    `path`='/authorization',
    `method`='GET,POST',
    `source`='system',
    `auth`='卡密+UDID',
    `handler_key`='license',
    `description`='查询授权信息；/license 保留兼容，若 Nginx 拦截请使用 /authorization',
    `updatetime`=UNIX_TIMESTAMP()
WHERE `endpoint_key`='license' AND (`source`='system' OR `source`='' OR `source` IS NULL);

-- Rename the former wall-clock token to persistent elapsed runtime.
UPDATE `fa_config`
SET `value`=REPLACE(`value`, '[服务器时间]', '[服务器运行时间]')
WHERE `name`='message' AND `value` LIKE '%[服务器时间]%';

-- Remove retired public announcement variables. Runtime normalization also
-- strips default-form variants such as [到期时间|xxx] for old templates.
UPDATE `fa_config` SET `value`=REPLACE(`value`, '[授权摘要]', '')
WHERE `name`='message' AND `value` LIKE '%[授权摘要]%';
UPDATE `fa_config` SET `value`=REPLACE(`value`, '[到期时间]', '')
WHERE `name`='message' AND `value` LIKE '%[到期时间]%';
UPDATE `fa_config` SET `value`=REPLACE(`value`, '[源名称]', '')
WHERE `name`='message' AND `value` LIKE '%[源名称]%';
UPDATE `fa_config` SET `value`=REPLACE(`value`, '[指定APP数量]', '')
WHERE `name`='message' AND `value` LIKE '%[指定APP数量]%';

UPDATE `fa_config` SET `value`=REPLACE(`value`, '[全源到期时间]', '')
WHERE `name`='message' AND `value` LIKE '%[全源到期时间]%';
UPDATE `fa_config` SET `value`=REPLACE(`value`, '[全源剩余时间]', '')
WHERE `name`='message' AND `value` LIKE '%[全源剩余时间]%';
UPDATE `fa_config` SET `value`=REPLACE(`value`, '[部分到期时间]', '')
WHERE `name`='message' AND `value` LIKE '%[部分到期时间]%';
UPDATE `fa_config` SET `value`=REPLACE(`value`, '[部分剩余时间]', '')
WHERE `name`='message' AND `value` LIKE '%[部分剩余时间]%';
UPDATE `fa_config` SET `value`=REPLACE(`value`, '[验证到期时间]', '')
WHERE `name`='message' AND `value` LIKE '%[验证到期时间]%';
UPDATE `fa_config` SET `value`=REPLACE(`value`, '[验证剩余时间]', '')
WHERE `name`='message' AND `value` LIKE '%[验证剩余时间]%';
