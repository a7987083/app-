-- Phase 17 / 2026091701 unlock response signing configuration.
-- Idempotent for repeated online-update execution on MySQL 5.7+.
-- The actual signing key is intentionally not stored in GitHub. App.php will
-- generate a random 32-byte key on first activation if this value is still blank.

INSERT INTO `fa_config`
    (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`)
SELECT
    'unlock_sign_key',
    'basic',
    '解锁签名KEY',
    '用于解锁响应HMAC-SHA256签名；留空时系统自动生成',
    'string',
    '',
    '',
    '',
    'autocomplete="off"'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `fa_config` WHERE `name` = 'unlock_sign_key'
);
