# ZONOE 软件源 2026092404

## 更新内容

本版本以 `source-v2026092403` 为升级基线，把 IPA 扫描与 IPA 解析统一到 PHP-FPM 当前进程内消费模型，并修复软件源保存成功却被误报失败的问题。

### IPA 解析执行链统一

- 开启自动解析后不再要求 CLI `ipa:parse-worker` 常驻，也不需要 Web 创建任何子进程。
- `IpaWorkerLauncher` 同时调度 Scan / Parse 两条 FPM consumer；HTTP 响应结束后由当前 FPM 进程继续消费队列。
- 扫描完成后若 `parse_enabled=1`，会继续解析新发现的 IPA，并执行软件源数据库比对。
- “继续解析/开启解析”会立即调度 Parse consumer，而不再只是修改设置值。
- “重试解析/重新解析”在自动解析开启时立即唤醒解析；暂停状态下仅回到待解析。
- 原 `php think ipa:parse-worker` 仍保留为可选兼容入口，但 Web 操作不依赖它。

### 状态刷新

- IPA 中心新增手动“刷新”按钮。
- 页面可见时每 5 秒自动刷新扫描任务与 IPA 资产表，扫描/解析进度无需整页刷新即可看到。
- 暂停/继续解析的按钮状态在原页面即时更新。

### 软件源保存/测试修复

- 修复 `IpaSourceCenter` 的 broad catch 捕获 FastAdmin 正常 `success()` 所抛 `think\exception\HttpResponseException`，造成“记录已经保存但页面仍提示保存失败”的问题。
- `add / edit / saveSource / testSource / deleteSource` 均改为只捕获真实业务异常，正常成功响应移到 catch 外。
- 保留原有配置语义：MySQL 软件源允许先保存配置，连接是否正确由“测试连接”单独判断。
- 测试连接失败会返回 PDO/MySQL 原始异常信息，便于定位主机、端口、账号、密码、库名或权限问题。

### 保留能力

- 保留 2026092403 的 OpenList FPM 内联扫描，不依赖 `proc_open`/`nohup`。
- 保留全量重扫、增量互斥、失败重试和扫描进度语义。
- 保留扫描任务清理、IPA 资产单条删除/全部清空、OpenList 来源显示和停用数据源筛选。
- 删除/清理继续不修改 `fa_category`，也不会删除 OpenList 上的实际 IPA 文件。

### 兼容性

- 不新增业务表结构或 SQL 迁移。
- PHP 7.0 / MySQL 5.7 兼容要求不变。
- 在线更新继续沿用现有 `UpdateManager / UpdateInstaller`，保留下载、SHA256、备份、SQL、文件覆盖、版本写入及失败回滚流程。

目标升级路径：`source-v2026092403 -> source-v2026092404`。
