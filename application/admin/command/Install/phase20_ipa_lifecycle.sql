-- Phase 20.7.4 ignore lifecycle permissions.
SET @now := UNIX_TIMESTAMP();
SET @gov := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/governance' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_lifecycle/ignored_list','查看忽略治理项','fa fa-circle-o','','Phase20.7 ignore lifecycle list',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_lifecycle/ignored_list');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_lifecycle/ignore_batch','批量忽略治理项','fa fa-circle-o','','Phase20.7 high-risk batch ignore',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_lifecycle/ignore_batch');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_lifecycle/unignore_batch','批量恢复治理项','fa fa-circle-o','','Phase20.7 batch unignore',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_lifecycle/unignore_batch');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_lifecycle/sweep_expired','扫描到期忽略项','fa fa-circle-o','','Phase20.7 ignore expiry sweep',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_lifecycle/sweep_expired');
