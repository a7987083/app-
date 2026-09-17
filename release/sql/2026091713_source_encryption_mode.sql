-- Phase 18.2 / 2026091713
-- Reuse fa_config.opencry as a backwards-compatible three-state selector:
-- 0 = plaintext, 1 = normal appstore encryption, 2 = appstore_v2 encryption.
-- The migration is idempotent and preserves valid existing 0/1 values.

INSERT IGNORE INTO `fa_config`
(`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`)
VALUES
('opencry','basic','软件源加密','关闭=明文；普通与V2互斥，只能选择一种加密协议','radio','0','{"0":"关闭","1":"普通","2":"V2"}','','');

UPDATE `fa_config`
SET
  `group` = 'basic',
  `title` = '软件源加密',
  `tip` = '关闭=明文；普通与V2互斥，只能选择一种加密协议',
  `type` = 'radio',
  `content` = '{"0":"关闭","1":"普通","2":"V2"}',
  `value` = CASE
      WHEN `value` IN ('0','1','2') THEN `value`
      ELSE '0'
  END
WHERE `name` = 'opencry';
