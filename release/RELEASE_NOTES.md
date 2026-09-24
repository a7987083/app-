# ZONOE 软件源 2026092402

## 更新内容

本版本以 `source-v2026092401` 为升级基线，修复 IPA Data Center 在宝塔 `open_basedir` 环境下扫描 Worker 自动启动失败的问题，并补齐扫描任务、IPA 资产与停用 OpenList 数据源的管理入口。

### Worker / open_basedir 修复

- 不再对 `/www/server/php/<ver>/bin/php` 调用 `is_file()` / `is_executable()`，避免站点 `open_basedir` 仅允许网站目录与 `/tmp` 时触发限制错误。
- 继续使用当前 PHP-FPM 自身提供的 `PHP_BINDIR/php` 作为 Worker CLI，避免误用系统 PHP 8.x。
- 在创建扫描 Job 之前先确认/拉起 Scan Worker；如果 Worker 启动失败，不再留下永久 `pending` Job 占用该数据源。
- Worker 仍继承 FPM 环境，包括已有 `PHP_IPA_SERVER_SECRET`。

### 扫描任务与 IPA 资产管理

- 新增“清理全部扫描任务”：先取消 `pending/running`，再清理扫描 Job/队列历史；不删除 IPA 资产、OpenList 数据源或 `fa_category`。
- IPA 资产新增单条删除和“清空全部 IPA 资产”。删除时同步清理解析尝试、二进制索引、数据库比对和分类绑定派生记录；不删除 OpenList 上的实际 IPA 文件，也不修改 `fa_category`。
- IPA 资产列表新增 OpenList 来源名称和 Source ID，明确资产来自 OpenList 扫描，而不是“软件源 MySQL”。
- OpenList 数据源默认隐藏已停用项，并提供“显示已停用/隐藏已停用”切换；停用继续保持“保留配置但不参与后续扫描/解析”的语义。

### 权限与兼容性

- 新增 MySQL 5.7 幂等权限迁移 `2026092402_ipa_cleanup_controls.sql`，补充 `clearScanJobs`、`deleteAsset`、`clearAssets` 三个 FastAdmin 权限项。
- 不新增业务表结构。
- 在线更新继续沿用现有 `UpdateManager / UpdateInstaller`，保留下载、SHA256、备份、SQL、文件覆盖、版本写入及失败回滚流程。

目标升级路径：`source-v2026092401 -> source-v2026092402`。
