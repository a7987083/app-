-- ZONOE 2026092402 - IPA cleanup controls
-- MySQL 5.7 compatible and idempotent.

SET @now := UNIX_TIMESTAMP();
SET @ipa_pid := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
VALUES
('file',@ipa_pid,'ipa_center/clearScanJobs','清理全部扫描任务','fa fa-trash','','',0,@now,@now,58,'normal'),
('file',@ipa_pid,'ipa_center/deleteAsset','删除 IPA 资产','fa fa-trash','','',0,@now,@now,57,'normal'),
('file',@ipa_pid,'ipa_center/clearAssets','清空全部 IPA 资产','fa fa-trash','','',0,@now,@now,56,'normal');
