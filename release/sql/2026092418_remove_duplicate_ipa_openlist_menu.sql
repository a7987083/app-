-- 2026092418: 删除重复的“软件源 -> 解析 IPA”独立入口。
-- 保留现有 IPA 总览/解析中心及其 OpenList 数据源配置功能。
-- 幂等执行：重复运行不会影响其它菜单。

DELETE FROM `fa_auth_rule`
WHERE `name` IN (
  'ipa_openlist/save',
  'ipa_openlist/test',
  'ipa_openlist/index'
);
