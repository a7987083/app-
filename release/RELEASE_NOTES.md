# ZONOE 软件源 2026092203

## 更新内容

本版本以 `source-v2026092202` 为升级基线，集中修复 IPA Data Center 在真实生产使用中暴露的解析调度、Worker 状态、数据源删除、错误详情和数据库对账问题；不改变原在线更新协议，不自动修改软件源数据库。

### 解析调度与 Worker

- 新增后台解析设置：可配置“每 N 分钟最多解析 N 个”、每小时上限、每日上限和失败冷却时间。
- Parse Worker 在领取 IPA 前执行 SecretBox 预检；服务器密钥缺失时直接停止 Worker，不再把 IPA 错误标记为 `parse_failed`。
- Worker 在线判断从固定 15 秒改为可配置宽限，默认 180 秒，避免单个 IPA 解析较久时误显示“未检测到”。
- 每次解析尝试写入 `fa_ipa_parse_attempt`，用于限速与审计。

### OpenList 扫描与删除

- 目录扫描继续固定 `refresh=false`，优先使用 OpenList 缓存。
- 参考稳定旧项目，单目录优先尝试 `page=1, per_page=0` 获取完整清单；不兼容的 OpenList 自动回退现有分页扫描。
- 数据源存在活动任务时，后台可选择“停止任务并删除”；会取消扫描任务/队列并清理 IPA 关联数据，不删除 `fa_category`。
- Worker 在写入扫描结果前重新检查数据源是否仍启用，避免删除后并发回写。

### 软件源分流

新增 IPA Data Center 子菜单“软件源”，用于管理一个或多个 MySQL 软件源：

- 名称、Slug、Host、Port、Database、Username、Password、应用表。
- 优先级、启用/停用。
- 默认只读；只有管理员明确开启“允许字段写回”后才能人工写数据库。
- 支持连接测试、编辑和删除；密码编辑时留空保持原值。

### IPA 自动比对与异常

- IPA 解析成功后自动与已启用 MySQL 软件源进行对比。
- 匹配优先级：下载地址精确匹配 > IPA 文件名匹配 > 应用名称+版本匹配。
- IPA 列表在“状态”旁新增“异常”列，可显示正常、未匹配、软件源错误或异常数量。
- “解析失败详情”和“异常详情”均可点击，展示 IPA 路径、Bundle、版本、错误信息及软件源对比结果。
- 异常详情显示数据库当前值与 IPA 建议值，可人工勾选字段写回。
- `name` / `bt1a` 等高风险字段默认不勾选；软件源为只读时完全禁止写回。
- 自动比对只生成差异，不自动修改任何数据库记录。

### 数据库

新增增量迁移：`release/sql/2026092203_ipa_ops_controls.sql`。

新增表：

- `fa_ipa_setting`
- `fa_ipa_parse_attempt`
- `fa_ipa_software_source`
- `fa_ipa_compare_result`

迁移保持 MySQL 5.7 兼容，并按重复执行设计。

### 在线更新

继续沿用现有 `UpdateManager / UpdateInstaller`：ZIP 下载、SHA256 校验、备份、执行增量 SQL、覆盖程序、文件校验、版本写入和失败回滚逻辑均不改变。

目标升级路径：`source-v2026092202 -> source-v2026092203`。
