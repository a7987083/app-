-- 2026092408: move Objective-C generator into Dylib Center UI.
-- Keep the existing permission nodes so users who already had access continue to work;
-- only remove the standalone menu entry. Safe to run repeatedly on MySQL 5.7.
SET @now := UNIX_TIMESTAMP();

UPDATE `fa_auth_rule`
SET `ismenu`=0,
    `title`='Dylib OC 接入代码服务',
    `remark`='2408 起界面内嵌于 Dylib 验证中心，不再作为独立菜单',
    `updatetime`=@now,
    `status`='normal'
WHERE `name`='general/occodegen';
