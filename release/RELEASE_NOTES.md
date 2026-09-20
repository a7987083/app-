# ZONOE 软件源 2026091915

## 基线

本版本以已发布并冻结的 `2026091914` 为直接基线。`2026091914` 及更早正式版本均不得复用或覆盖 Release Asset；本次后台任务执行模型修复进入新版本 `2026091915`。

## 更新内容

- 按旧项目成熟的后台任务职责模型重构 IPA 扫描：Web 只创建持久任务，不再通过 `exec/nohup php think ...` 临时 fork CLI worker。
- 新增 `fa_ipa_worker_job` 持久任务队列和 `fa_ipa_worker_state` heartbeat 状态表，scan / parse 由数据库原子 claim，避免重复消费和任务丢失。
- 新增 `ipa:worker` 常驻 Worker 命令，支持持续消费以及 `--once` 单任务诊断模式。
- 为未部署常驻 Worker 的站点增加 PHP-FPM after-response fallback：HTTP 响应完成后在同一 Web 进程消费 1 个持久 job，对应旧 Node 项目的 `setImmediate(runQueuedTask)` 语义，不依赖 CLI PHP 路径。
- `IpaScanService::spawn()` / `IpaParserService::spawn()` 保留为兼容入口，但内部仅入队，源码已移除 `exec/nohup` 临时 PHP worker。
- “后台解析 1 个”继续严格只消费 1 个 pending IPA；MD5 parse cache、30 分钟失败退避、`needs_reparse` 并发保护保持不变。
- 后台扫描继续严格使用启用 MySQL 软件源中的 `bt1a` 作为权威范围，沿用 30 分钟 OpenList 目录缓存和 MD5 优先差异判定。
- 新增 `Phase 20 Persistent IPA Worker` CI Gate：PHP 7.0 语法/契约校验 + MySQL 5.7 worker migration 双跑及索引验证。
- 在线更新包加入 Worker Service、Worker Command 和 `phase20_ipa_worker.sql`，升级时先建队列表再覆盖新程序文件。

## 预发布验证

- PR #15 `Phase 20.9 persistent IPA worker`：Regression Checks、Phase14 Production Hardening、Phase 17.2 Authorization Integrity 全部成功。
- `Phase 20 IPA Management`：contract、integration、package、MySQL 5.7 全部成功。
- `Phase 20 Workset MySQL57` 成功。
- 新 `Phase 20 Persistent IPA Worker`：worker-contract 与 worker-mysql57 全部成功；Worker migration 在真实 MySQL 5.7 连续执行两遍通过。

## 发布后验证

- GitHub Release 必须创建全新的 `source-v2026091915`，不得覆盖 `source-v2026091914` 或更早版本。
- 在线升级 E2E 必须验证 `2026091914 -> 2026091915`。
- 升级后手工点击“后台扫描 MD5（使用缓存）”时，任务应由 `queued` 很快进入 `running/read_references`，不再依赖 `php think ipa:scan` 临时 CLI fork。
- 真实生产服务器仍需升级后单独验证 MySQL 软件源 → OpenList 缓存/MD5 → metadata pending → 后台解析 1 个 → binding 的完整链路。
