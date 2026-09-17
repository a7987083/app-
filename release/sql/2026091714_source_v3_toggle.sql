-- Phase 18.3 / 2026091714
-- V3 source synchronization feature toggle.
-- 0 = disabled, 1 = enabled.
-- Default to enabled so upgrading from 1712/1713 preserves current V3 behavior.

INSERT IGNORE INTO `fa_config`
(`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`)
VALUES
('source_v3','basic','V3软件源','开启后客户端优先使用V3；V3不可用或明确不支持时回退到原appstore接口','switch','1','','','');

UPDATE `fa_config`
SET
  `group` = 'basic',
  `title` = 'V3软件源',
  `tip` = '开启后客户端优先使用V3；V3不可用或明确不支持时回退到原appstore接口',
  `type` = 'switch',
  `value` = CASE WHEN `value` IN ('0','1') THEN `value` ELSE '1' END,
  `content` = '',
  `rule` = ''
WHERE `name` = 'source_v3';
