-- Phase 20.3 parser permissions.
SET @now := UNIX_TIMESTAMP();
SET @ipa_metadata := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center/metadata' LIMIT 1);
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_metadata,'ipa_center/metadata_list','IPA 元数据列表','fa fa-circle-o','','Phase20 parser read permission',0,@now,@now,0,'normal'
WHERE @ipa_metadata IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/metadata_list');
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',@ipa_metadata,'ipa_center/parse_start','启动 IPA Range 解析','fa fa-circle-o','','Phase20 parser execute permission',0,@now,@now,0,'normal'
WHERE @ipa_metadata IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='ipa_center/parse_start');
