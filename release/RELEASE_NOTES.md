# ZONOE 软件源 2026092202

## 更新内容

本版本以 `source-v2026092201` 为升级基线，属于 IPA Data Center 管理与解析稳定性热修；不改变原在线更新协议、Category 写回原则或 Dylib 验证协议。

### 数据源管理

- OpenList 数据源支持编辑：名称、URL、根目录、启用状态、扫描分页、请求超时；Token 编辑时留空保持原值。
- 新增安全删除数据源：有活动扫描/解析任务时拒绝删除；删除时清理该源的扫描任务、队列、IPA 资产、Mach-O 索引和 IPA 绑定记录，不删除 `fa_category`。
- OpenList 目录扫描继续固定 `refresh=false`，优先使用 OpenList 缓存。

### 解析管理

- IPA 资产明确显示 `discovered / parsing / parsed / parse_failed` 状态。
- 已解析 IPA 支持重新解析，失败 IPA 支持重试解析。
- Scan Worker 与 Parse Worker 新增心跳状态，后台可直接看到 Worker 是否运行。

### 128MB PHP 解析稳定性

修复 PHP 7.0 `memory_limit=128M` 下大 Mach-O 导致 Worker OOM：

- `Info.plist` 元数据仍优先通过 HTTP Range 完成。
- Mach-O architecture / load commands 改为最多 2 MiB 的 bounded prefix 读取。
- deflate ZIP member 使用 `inflate_init / inflate_add` 分块解压前缀，不再一次性加载完整几十 MB 二进制。
- 完整 SHA256 只同步处理 <=8 MiB 的二进制成员；大二进制仍记录路径、类型、大小及可获得的头部信息，不阻塞 IPA 元数据解析。

### 数据库与权限

新增增量迁移：

- `release/sql/2026092202_ipa_management_hotfix.sql`
- `fa_ipa_worker_state`
- `ipa_center/deleteSource`
- `ipa_center/retryParse`

迁移兼容 MySQL 5.7，并按重复执行设计。

### 在线更新

继续沿用现有 `UpdateManager / UpdateInstaller`：下载 ZIP、SHA256 校验、备份、执行 `mysql/*.sql`、覆盖 `program/*`、覆盖后文件校验、版本写入和失败回滚逻辑均不改变。

目标升级路径：`source-v2026092201 -> source-v2026092202`。
