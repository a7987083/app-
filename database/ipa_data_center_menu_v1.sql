-- FastAdmin menu/permission nodes for IPA Data Center v1
-- Idempotent by unique fa_auth_rule.name.
SET @now := UNIX_TIMESTAMP();

INSERT IGNORE INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
VALUES ('file',0,'ipa_center','IPA 数据中心','fa fa-archive','','IPA / Dylib 数据与验证中心',1,@now,@now,120,'normal');

SET @ipa_pid := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='ipa_center' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
VALUES
('file',@ipa_pid,'ipa_center/index','总览','fa fa-dashboard','','',0,@now,@now,100,'normal'),
('file',@ipa_pid,'ipa_center/assets','IPA 资产','fa fa-file-archive-o','','',0,@now,@now,90,'normal'),
('file',@ipa_pid,'ipa_center/jobs','扫描任务','fa fa-tasks','','',0,@now,@now,80,'normal'),
('file',@ipa_pid,'ipa_center/saveSource','保存 OpenList 数据源','fa fa-database','','',0,@now,@now,70,'normal'),
('file',@ipa_pid,'ipa_center/startScan','启动扫描','fa fa-play','','',0,@now,@now,60,'normal');
