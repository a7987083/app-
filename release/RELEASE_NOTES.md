# ZONOE 软件源 2026092403

## 更新内容

本版本以 `source-v2026092402` 为升级基线，撤销 2026092401/2026092402 中“Web 请求通过 `proc_open` 拉起 PHP CLI 扫描 Worker”的运行方式，恢复 OpenList 扫描不依赖子进程的架构。

### OpenList 扫描执行链修复

- `IpaWorkerLauncher` 不再调用 `proc_open`、`nohup`、`/dev/null` 或 `PHP_BINDIR/php` 启动 CLI 子进程。
- 后台点击全量/增量扫描后仍使用既有 `ipa_scan_job / ipa_scan_item` 队列，保留全量重扫、增量互斥、失败重试和任务进度语义。
- HTTP 请求完成时注册 PHP shutdown consumer；在 PHP-FPM 环境下先通过 `fastcgi_finish_request()` 返回页面，再由当前 FPM 进程继续 `claimOne -> processItem` 消费扫描队列。
- OpenList Token 直接使用当前 FPM 环境中的既有 `PHP_IPA_SERVER_SECRET` 解密，不再存在 FPM 与 CLI 环境密钥不一致的问题。
- 临时 OpenList 错误进入既有指数退避重试；FPM consumer 在仍有 pending item 时继续等待并消费，避免失败后再次留下长期 pending 锁。
- 现有 `php think ipa:worker` 命令继续保留为可选的外部队列消费者，但 Web 扫描不再要求它存在或运行。

### 保留 2026092402 管理能力

- 保留“清理全部扫描任务”。
- 保留 IPA 资产单条删除与“清空全部 IPA 资产”。
- 保留 IPA 资产 OpenList 来源名称 / Source ID 显示。
- 保留 OpenList 默认隐藏已停用项及“显示已停用”切换。
- 删除/清理操作继续不修改 `fa_category`，也不会删除 OpenList 上的实际 IPA 文件。

### 兼容性

- 不新增业务表结构或 SQL 迁移。
- PHP 7.0 / MySQL 5.7 兼容要求不变。
- 在线更新继续沿用现有 `UpdateManager / UpdateInstaller`，保留下载、SHA256、备份、SQL、文件覆盖、版本写入及失败回滚流程。

目标升级路径：`source-v2026092402 -> source-v2026092403`。
