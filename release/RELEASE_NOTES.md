# ZONOE 软件源 2026091705

## 更新内容

- 修复升级到 2026091704 后“编辑 App”可能进入错误页的问题。
- 后台新增/编辑应用时会先检测 `fa_category.renewal_entry`；若字段缺失会尝试自修复，修复失败时仅隐藏续费入口选项，不再让整个编辑页报错。
- 新增 `2026091705_renewal_entry_repair.sql`，再次修复遗漏的续费入口字段，且不依赖 `bt2b` 字段位置。
- 修复“清空全部换绑记录”按钮点击后没有真正删除的问题。
- 修复“清空全部授权事件”按钮点击后没有真正删除的问题。
- 两个清空操作改成原生 HTML POST 表单，不再依赖后台页面内联 jQuery/`$.ajax` 是否被执行。
- 清空成功后由 FastAdmin/ThinkPHP 后端原生 `success()` 返回并跳回对应页面，同时显示实际删除条数。
- 清空换绑记录仍只删除 `fa_card_transfer_log`，不修改卡密、当前授权、到期时间和剩余换绑次数。
- 清空授权事件仍只删除 `fa_authorization_event`，不修改卡密和当前授权。

## 续费入口兼容性

- 保留 2026091704 的“续费入口 App”行为：`renewal_entry=1` 时始终 `lock=1`、`downloadURL=''`，只用于客户端现有“解锁 → 输入卡密”续期流程。
- 卡密仍然只负责一次性消费；输入后继续按 `card_scope` 给当前 UDID 对应授权链叠加时间。
- 不改变全软件源、仅验证、指定 App 三类卡密的授权含义和叠加规则。

## 数据库

- 新迁移：`release/sql/2026091705_renewal_entry_repair.sql`。
- 兼容 MySQL 5.7，可重复执行。
- 本版本专项测试不是只用 `mysqli::multi_query()`，而是先经过线上 `UpdateSqlRunner::splitStatements()` 分句，再按顺序逐句执行两次，以覆盖实际在线更新的 SQL 执行语义。

## 测试

- PHP 7.0：Category/Authorization 控制器语法检查通过。
- Phase 17.4 后台契约测试通过。
- MySQL 5.7：按在线更新 SQL 分句语义连续执行两次通过。
- 在线更新清单新增 `application/admin/controller/Category.php`，保证修复不会只存在于 GitHub 源码而漏进更新包。

## 在线更新

- 正式版本：`2026091705`
- GitHub Release：`source-v2026091705`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
- 后台 GitHub 在线更新可从 `2026091704` 升级到 `2026091705`。
- 发布门禁继续包含 PHP 7.0 回归、MySQL 5.7 迁移、ZIP 内容检查、SHA256 和真实 GitHub Release 在线升级 E2E。
