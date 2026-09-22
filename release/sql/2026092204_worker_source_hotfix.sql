-- ZONOE 2026092204 - IPA worker/source hotfix
-- MySQL 5.7 compatible and idempotent. Upgrade target: source-v2026092203.

SET @now := UNIX_TIMESTAMP();
SET @ipa_pid := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`) VALUES
('file',@ipa_pid,'ipa_source_center/add','新增软件源','fa fa-plus','','FastAdmin 原生新增表单',0,@now,@now,93,'normal'),
('file',@ipa_pid,'ipa_source_center/edit','编辑软件源','fa fa-pencil','','FastAdmin 原生编辑表单',0,@now,@now,92,'normal'),
('file',@ipa_pid,'ipa_source_center/del','删除软件源','fa fa-trash','','FastAdmin 原生删除动作',0,@now,@now,91,'normal');

-- 保留 2026092203 的 saveSource/deleteSource 权限节点用于升级兼容，
-- 2026092204 前端不再依赖这两个旧动作。
