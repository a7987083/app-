# ZONOE 软件源 2026091916

## 基线

本版本以已发布并冻结的 `2026091915` 为直接基线。`2026091915` 及更早正式版本均不得复用或覆盖 Release Asset；本次仅修复 1915 Persistent Worker migration 未进入在线更新包的发布集成缺口。

## 修复内容

- 保持 2026091915 Persistent Worker 运行模型不变，不回退 Worker Service / Worker Command / PHP-FPM after-response fallback。
- 修复 `tools/build_online_update.php`：把 `application/admin/command/Install/phase20_ipa_worker.sql` 登记为 `mysql/2026091915_phase20_ipa_worker.sql`，恢复 1912/1914 已有的 Phase20 migration 打包方式。
- 更新 `phase20_rc_release_contract_test.php`：Worker Command、Worker Service 和 worker migration 必须同时存在于发布 manifest / builder 契约中。
- `phase20_ipa_worker.sql` 继续使用 MySQL 5.7 兼容且幂等的 `CREATE TABLE IF NOT EXISTS`，用于创建 `fa_ipa_worker_job` 与 `fa_ipa_worker_state`。
- 不修改 UpdateIntegrity 四个哨兵文件，`file_sign` 保持 `8be29c04c34ba1d1cc7ec77d392b2bee`。

## 根因

`2026091915` 新增了 Worker SQL、Worker CI 和程序文件，并把 SQL 源文件加入 `release/online-update-files.txt`，但没有同步把该 migration 加入 `tools/build_online_update.php::$phase20Sql`；原有 RC contract 的 `$orderedSql` 也停留在 `2026091911_phase20_ipa_workset.sql`，因此 CI 验证了 SQL 本身，却没有阻止最终 Release ZIP 漏包。

## 发布验证

- 正式 Release 使用既有 `ZONOE Source Release` workflow，不引入新的发布机制。
- PHP 7.0 regression、MySQL 5.7 migration、Phase 20 integration、Persistent IPA Worker gate 必须成功。
- 最终 `zonoe-online-update.zip` 必须包含 `mysql/2026091915_phase20_ipa_worker.sql`。
- 在线升级 E2E 必须验证上一正式版本 `2026091915 -> 2026091916`。

## 发布后验证

- GitHub Release 必须创建全新的 `source-v2026091916`，不得覆盖 `source-v2026091915` 或更早版本。
- 生产升级后必须确认 `fa_ipa_worker_job` 与 `fa_ipa_worker_state` 存在。
- 手工创建一次后台扫描/解析任务，确认任务可从 `queued` 被 Worker/FPM fallback 消费，并进入正常运行/完成状态。
