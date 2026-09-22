-- ZONOE 2026092205 - IPA Center UI controls hotfix
-- MySQL 5.7 compatible and idempotent.

SET @now := UNIX_TIMESTAMP();
SET @ipa_pid := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
VALUES
('file',@ipa_pid,'ipa_center/saveParseSettings','保存解析设置','fa fa-save','','',0,@now,@now,69,'normal'),
('file',@ipa_pid,'ipa_center/pauseParse','暂停解析','fa fa-pause','','',0,@now,@now,68,'normal'),
('file',@ipa_pid,'ipa_center/resumeParse','继续解析','fa fa-play','','',0,@now,@now,67,'normal'),
('file',@ipa_pid,'ipa_center/clearParseResults','清空解析结果','fa fa-trash','','',0,@now,@now,66,'normal'),
('file',@ipa_pid,'ipa_center/deleteSource','删除 OpenList 数据源','fa fa-trash','','',0,@now,@now,65,'normal'),
('file',@ipa_pid,'ipa_center/forceDeleteSource','停止任务并删除 OpenList 数据源','fa fa-stop','','',0,@now,@now,64,'normal'),
('file',@ipa_pid,'ipa_center/retryParse','重新解析 IPA','fa fa-refresh','','',0,@now,@now,63,'normal'),
('file',@ipa_pid,'ipa_center/assetDetail','IPA 异常详情','fa fa-eye','','',0,@now,@now,62,'normal'),
('file',@ipa_pid,'ipa_center/compareAsset','重新比对软件源','fa fa-exchange','','',0,@now,@now,61,'normal'),
('file',@ipa_pid,'ipa_center/applyCompareWriteback','异常字段手动写回','fa fa-pencil','','',0,@now,@now,59,'normal');
