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
('file',@ipa_pid,'ipa_center/index','总览','fa fa-dashboard','','',1,@now,@now,100,'normal'),
('file',@ipa_pid,'dylib_center/index','Dylib 验证中心','fa fa-shield','','',1,@now,@now,95,'normal'),
('file',@ipa_pid,'ipa_center/assets','IPA 资产','fa fa-file-archive-o','','',0,@now,@now,90,'normal'),
('file',@ipa_pid,'ipa_center/jobs','扫描任务','fa fa-tasks','','',0,@now,@now,80,'normal'),
('file',@ipa_pid,'ipa_center/categorySearch','搜索项目','fa fa-search','','',0,@now,@now,75,'normal'),
('file',@ipa_pid,'ipa_center/writebackPreview','写回预览','fa fa-eye','','',0,@now,@now,74,'normal'),
('file',@ipa_pid,'ipa_center/writebackApply','手动写回项目','fa fa-exchange','','',0,@now,@now,73,'normal'),
('file',@ipa_pid,'ipa_center/saveSource','保存 OpenList 数据源','fa fa-database','','',0,@now,@now,70,'normal'),
('file',@ipa_pid,'ipa_center/deleteSource','删除 OpenList 数据源','fa fa-trash','','',0,@now,@now,69,'normal'),
('file',@ipa_pid,'ipa_center/retryParse','重新解析 IPA','fa fa-repeat','','',0,@now,@now,68,'normal'),
('file',@ipa_pid,'ipa_center/startScan','启动扫描','fa fa-play','','',0,@now,@now,60,'normal'),
('file',@ipa_pid,'dylib_center/versions','Dylib 版本列表','fa fa-code-fork','','',0,@now,@now,55,'normal'),
('file',@ipa_pid,'dylib_center/bindings','Dylib 游戏绑定','fa fa-link','','',0,@now,@now,54,'normal'),
('file',@ipa_pid,'dylib_center/logs','Dylib 验证日志','fa fa-list-alt','','',0,@now,@now,53,'normal'),
('file',@ipa_pid,'dylib_center/generateVerifySecret','生成 Dylib 验证密钥','fa fa-key','','',0,@now,@now,52,'normal'),
('file',@ipa_pid,'dylib_center/saveDylib','保存 Dylib','fa fa-save','','',0,@now,@now,51,'normal'),
('file',@ipa_pid,'dylib_center/saveVersion','保存 Dylib 版本','fa fa-save','','',0,@now,@now,50,'normal'),
('file',@ipa_pid,'dylib_center/saveBinding','保存 Dylib 绑定','fa fa-save','','',0,@now,@now,49,'normal');
