-- Phase 20.5 write-back template permissions.
SET @now := UNIX_TIMESTAMP();
SET @writeback := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/writeback' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@writeback,'ipa_center/writeback_rules','读取写库模板','fa fa-circle-o','','Phase20.5 writeback template read',0,@now,@now,0,'normal'
WHERE @writeback IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/writeback_rules');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@writeback,'ipa_center/writeback_seed','初始化写库模板','fa fa-circle-o','','Phase20.5 writeback template mutation',0,@now,@now,0,'normal'
WHERE @writeback IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/writeback_seed');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@writeback,'ipa_center/writeback_save','保存写库模板版本','fa fa-circle-o','','Phase20.5 writeback template mutation',0,@now,@now,0,'normal'
WHERE @writeback IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/writeback_save');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@writeback,'ipa_center/writeback_random_preview','随机样本写库预览','fa fa-circle-o','','Phase20.5 writeback preview',0,@now,@now,0,'normal'
WHERE @writeback IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/writeback_random_preview');
