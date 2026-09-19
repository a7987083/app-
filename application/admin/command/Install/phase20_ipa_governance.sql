-- Phase 20.6/20.7 governance action permissions.
SET @now := UNIX_TIMESTAMP();
SET @gov := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/governance' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_center/governance_refresh','重新检测治理异常','fa fa-circle-o','','Phase20.6 governance refresh',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_refresh');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_center/governance_list','查看治理异常','fa fa-circle-o','','Phase20.6 governance list',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_list');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_center/governance_preview','治理修复预览','fa fa-circle-o','','Phase20.6 governance preview',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_preview');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_center/governance_apply','执行治理修复','fa fa-circle-o','','Phase20.6 high-risk governance apply',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_apply');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_center/governance_ignore','忽略治理异常','fa fa-circle-o','','Phase20.6 governance ignore',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_ignore');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_center/governance_verify','重新验证治理异常','fa fa-circle-o','','Phase20.6 governance verify',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_verify');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_center/governance_batch_preview','批量治理预览','fa fa-circle-o','','Phase20.7 safe batch governance preview',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_batch_preview');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_center/governance_batch_apply','执行批量治理','fa fa-circle-o','','Phase20.7 high-risk batch governance apply',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_batch_apply');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_center/governance_failures','查看治理失败队列','fa fa-circle-o','','Phase20.7 governance failure queue',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/governance_failures');
