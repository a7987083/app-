-- Phase 20.7.3 production metrics / retention permissions.
SET @now := UNIX_TIMESTAMP();
SET @gov := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/governance' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_production/metrics','查看 IPA Range 指标','fa fa-circle-o','','Phase20.7 range metrics',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_production/metrics');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_production/retention_preview','Retention 清理预览','fa fa-circle-o','','Phase20.7 retention preview',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_production/retention_preview');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@gov,'ipa_production/retention_apply','执行 Retention 清理','fa fa-circle-o','','Phase20.7 high-risk retention cleanup',0,@now,@now,0,'normal'
WHERE @gov IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_production/retention_apply');
