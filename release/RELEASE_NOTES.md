# ZONOE 软件源 2026092207

## 更新内容

本版本以 `source-v2026092206` 为升级基线，修复 IPA Data Center 控制链在扫描、解析暂停/继续、配额限制和解析清理上的回归问题。该版本继续沿用现有在线更新协议，不新增数据库迁移，不自动修改 `fa_category` 或软件源目标数据库。

### 扫描控制

- 全量扫描恢复“重新扫描”语义：若同一数据源已有 pending/running 扫描任务，旧任务会被取消并创建新的 full scan。
- 增量扫描继续保持同一数据源互斥，避免并发重复扫描。
- 扫描流程增加协作式取消检查；已取消任务不会再执行最终 full-scan 缺失标记收口。

### 解析控制

- 移除每 5 分钟、每小时、每日解析数量限制的强制拦截；`parse_enabled` 继续作为暂停/继续解析总开关。
- IPA Data Center 页面移除新增的扫描 Worker 状态和解析配额展示，恢复旧版控制面表现。
- 修复 FastAdmin/ThinkPHP 正常 success 响应被宽泛异常捕获的问题，暂停、继续、启动扫描和清空解析不再把 `think\exception\HttpResponseException` 当作业务失败展示。

### 清空解析

- 清空解析继续只删除解析派生结果、Mach-O/二进制索引、数据库比对结果和解析尝试记录。
- 已解析或解析失败的 IPA 恢复为 `discovered`。
- OpenList 扫描/发现记录、软件源配置和 `fa_category` 保持不变。

### 兼容性与验证

- PHP 7.0 语法与运行契约通过。
- MySQL 5.7 既有迁移与并发/规模测试通过；本版本无新增 SQL。
- Mach-O / FAT Mach-O / IPA bounded parser、扫描安全、管理与内存安全、运维控制和回归检查通过。
- 2026092207 正式在线更新 Gate 用于验证真实 ZIP、SHA256、既有 SQL 和版本元数据一致性。

### 在线更新

继续沿用现有 `UpdateManager / UpdateInstaller`：ZIP 下载、SHA256 校验、备份、既有增量 SQL、文件覆盖、文件校验、版本写入和失败回滚逻辑均不改变。

目标升级路径：`source-v2026092206 -> source-v2026092207`。
