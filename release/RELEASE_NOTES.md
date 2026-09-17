# ZONOE 软件源 2026091710

## 更新内容

### 修复从 2026091704 开始的 `/appstore` 可用性回归

- 已确认 `/appstore` 路由本身在 2026091703 与 2026091704 完全一致，仍为 `appstore -> index/App/list`；问题不是路由被删除。
- 2026091704 首次把 `renewal_entry` 加入 `SourceAppRecord::publicSourceColumns()`，导致 `/appstore` 每次查询 `fa_category` 时都强制 SELECT `renewal_entry`。
- 如果生产库的 `2026091704_renewal_entry.sql` 没有成功新增该字段，MySQL 会直接报不存在字段，ThinkPHP 返回异常页，因此整个软件源无法访问。
- 2026091710 将 `renewal_entry` 改为**运行时可选字段**：先检测 `fa_category` 是否真实存在该列；存在则继续启用续费入口，不存在则自动排除该字段并按旧版 App 数据返回。
- 数据库迁移是否成功不再决定 `/appstore` 是否可访问。即使旧服务器漏跑迁移，软件源也应继续工作。
- 续费入口功能没有删除：字段存在时仍读取 `renewal_entry`，`renewal_entry=1` 仍保持 `lock=1`、`downloadURL=''`。
- 不修改 `appstore` / `appstore_v2` 加密协议，不修改客户端 JSON 字段，不要求客户端更新。

## 影响版本

- `2026091703`：最后一个没有 `renewal_entry` 强制查询依赖的版本。
- `2026091704`：首次引入该依赖，是本次回归起点。
- `2026091705` ～ `2026091709`：继承了相同的源查询字段依赖；即使后续增加修复迁移，只要生产库字段仍缺失，`/appstore` 仍可能失败。
- `2026091710`：首次把源接口改为 schema-adaptive，不再因 `renewal_entry` 缺失而整体不可用。

## 测试

- PHP 7.0：`SourceAppRecord::publicSourceColumns()` 在运行时检测不到 `renewal_entry` 时必须自动排除该字段。
- MySQL 5.7：先创建不含 `renewal_entry` 的旧 `fa_category`，验证兼容 SELECT 可以正常执行；随后运行续费入口迁移，再验证字段会自动重新进入源查询列。
- 保留既有续费入口、卡密/App 映射清理、授权总览刷新、`apiface` 签名和三种卡密用途回归。
- 正式发布继续要求 PHP 7.0 全回归、MySQL 5.7 迁移、ZIP/SHA256 和真实 GitHub Release 在线升级 E2E 全部通过。

## 在线更新

- 正式版本：`2026091710`
- 基线：`2026091709`
- GitHub Release：`source-v2026091710`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
- 2026091704 ～ 2026091709 的服务器均应升级到 2026091710；本版不会删除业务数据。
