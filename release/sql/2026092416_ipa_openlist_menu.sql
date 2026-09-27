-- 2026092416: 软件源 -> 解析 IPA（第一阶段 OpenList Token 连接）
-- 幂等执行：重复运行不会重复创建菜单。

SET @now := UNIX_TIMESTAMP();

-- 优先寻找名称明确为“软件源”的现有菜单；
-- 兼容旧安装：若名称不同，则沿用 category/index 当前所在的父菜单。
SET @source_menu_id := (
    SELECT id FROM `fa_auth_rule`
    WHERE `type`='menu' AND `title`='软件源'
    ORDER BY id ASC LIMIT 1
);
SET @source_menu_id := COALESCE(
    @source_menu_id,
    (SELECT pid FROM `fa_auth_rule` WHERE `name`='category/index' ORDER BY id ASC LIMIT 1),
    0
);

INSERT INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'menu',@source_menu_id,'ipa_openlist/index','解析 IPA','fa fa-archive','','','OpenList Token 连接与 IPA 解析入口',1,@now,@now,120,'normal'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_openlist/index');

SET @ipa_menu_id := (SELECT id FROM `fa_auth_rule` WHERE `name`='ipa_openlist/index' ORDER BY id ASC LIMIT 1);

INSERT INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_menu_id,'ipa_openlist/save','保存 OpenList 配置','fa fa-circle-o','','','',0,@now,@now,0,'normal'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_openlist/save');

INSERT INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_menu_id,'ipa_openlist/test','测试 OpenList 连接','fa fa-circle-o','','','',0,@now,@now,0,'normal'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_openlist/test');
