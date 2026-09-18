-- Phase 20.0 IPA management center menu/auth skeleton.
-- Idempotent: rule.name is unique and each insert is guarded.
SET @now := UNIX_TIMESTAMP();

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',0,'ipa_center','IPA 管理中心','fa fa-cubes','','Phase 20 IPA management center',1,@now,@now,118,'normal'
WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center');
SET @ipa_parent := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_parent,'ipa_center/index','总览','fa fa-dashboard','','',1,@now,@now,70,'normal' WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/index');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_parent,'ipa_center/metadata','IPA 元数据','fa fa-file-archive-o','','',1,@now,@now,60,'normal' WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/metadata');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_parent,'ipa_center/binding','IPA 绑定','fa fa-link','','',1,@now,@now,50,'normal' WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/binding');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_parent,'ipa_center/governance','数据治理','fa fa-wrench','','',1,@now,@now,40,'normal' WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_parent,'ipa_center/task','扫描任务','fa fa-tasks','','',1,@now,@now,30,'normal' WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/task');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_parent,'ipa_center/writeback','解析与写库','fa fa-database','','',1,@now,@now,20,'normal' WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/writeback');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_parent,'ipa_center/setting','设置','fa fa-cog','','',1,@now,@now,10,'normal' WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/setting');
