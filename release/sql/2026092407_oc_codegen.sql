-- 2026092407 Objective-C generator admin entry and permissions.
-- No business data is modified. Re-running is idempotent through INSERT IGNORE.
SET @now := UNIX_TIMESTAMP();

INSERT IGNORE INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
VALUES
('file',0,'general/occodegen','OC 工程生成','fa fa-code','','根据当前软件源 API 配置预览并生成 Objective-C 工程文件',1,@now,@now,88,'normal');

SET @oc_codegen_pid := (SELECT `id` FROM `fa_auth_rule` WHERE `name`='general/occodegen' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`) VALUES
('file',@oc_codegen_pid,'general/occodegen/index','生成器页面','fa fa-code','','',0,@now,@now,100,'normal'),
('file',@oc_codegen_pid,'general/occodegen/current','读取当前配置','fa fa-refresh','','',0,@now,@now,90,'normal'),
('file',@oc_codegen_pid,'general/occodegen/preview','生成预览','fa fa-eye','','',0,@now,@now,80,'normal'),
('file',@oc_codegen_pid,'general/occodegen/generate','生成 ZIP','fa fa-file-archive-o','','',0,@now,@now,70,'normal'),
('file',@oc_codegen_pid,'general/occodegen/download','下载 ZIP','fa fa-download','','',0,@now,@now,60,'normal');
