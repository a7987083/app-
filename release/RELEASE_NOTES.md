# ZONOE 软件源 2026091914

## 基线

本版本以已发布并冻结的 `2026091913` 为直接基线。`2026091911`、`2026091912`、`2026091913` 均不得复用或覆盖 Release Asset；本次所有变化进入新版本 `2026091914`。

## 更新内容

- IPA 扫描范围改为严格由启用 MySQL 软件源中的 `bt1a` 决定，移除无引用源时扫描整个 OpenList 目录的 fallback。
- 保留旧项目成熟的 OpenList 目录缓存 / MD5 优先判定思路：普通后台 MD5 扫描优先使用缓存，强制刷新目录独立执行。
- Parser 固定每次后台解析 `1` 个 IPA，并加入原子领取、`needs_reparse` 并发保护、30 分钟失败退避。
- 新增独立 `fa_ipa_parse_cache`，按 `MD5 + 文件大小 + Parser Version` 复用解析结果；复用时同时恢复完整 metadata payload，而不是只恢复基础列。
- `fa_ipa_metadata` 收敛为当前 active workset，引入 `referenced / needs_reparse`；无引用且未绑定的历史数据在解析结果入 cache 后可安全回收。
- 修复 IPA 元数据筛选查询：count 与 rows 分别构建 Query，避免 ThinkPHP/PDO 绑定参数复用导致的查询失败。
- 元数据刷新失败会明确提示当前显示旧数据，不再让 BootstrapTable 静默保留旧 rows。
- OpenList 停用后扫描、强制刷新、Range Parser、连接测试均拒绝执行；历史健康结果只作为“上次检查”展示。
- 修复从未启动、`heartbeat_at=0` 的 queued 僵尸任务恢复；后台 spawn 改为优先 CLI PHP 并对启动结果做额外校验。
- 新增 `Phase 20 Workset MySQL57` CI Gate，在真实 MySQL 5.7 上连续执行最新 Phase20 migration 两遍并验证 workset/cache schema。

## 预发布验证

- PR #14 `Phase 20 IPA Management` Run #118：`phase20-contract`、`phase20-integration`、`phase20-mysql57`、`phase20-package` 全部成功。
- 同一 PR HEAD 的 `Regression Checks`、`Phase14 Production Hardening`、`Phase 17.2 Authorization Integrity` 全部成功。
- `Phase 20 Workset MySQL57` Run #1 成功；最新 Phase20 migration 在 MySQL 5.7 连续执行两遍通过。

## 发布后验证

- GitHub Release 应创建全新的 `source-v2026091914`，不得覆盖 `source-v2026091913` 或更早版本。
- 在线升级 E2E 必须验证 `2026091913 -> 2026091914` 的发布链路。
- 真实生产服务器目前只确认到 `2026091911`；正式 CI 全绿并发布 1914 后，生产升级与 Phase 20 功能仍需单独受控验证。
