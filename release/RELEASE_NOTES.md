# ZONOE 软件源 2026091917

## 基线

本版本以已发布但在线更新 E2E 未完成的 `2026091916` 为直接基线。`2026091916` 及更早 Release Tag/Asset 均保持不变；本次继续前滚修复发布契约，不覆盖历史 Release。

## 更新内容

- 保留 2026091916 已完成的 Persistent Worker migration 打包修复：`phase20_ipa_worker.sql` 继续以 `mysql/2026091915_phase20_ipa_worker.sql` 进入在线更新包。
- 保留 Release Contract 对 Worker Command、Worker Service 和 worker migration 的强制检查。
- 修复在线升级 E2E 的历史 Release Notes 契约：正式 Release Notes 必须包含 `更新内容` 标题；2026091916 使用了 `修复内容`，导致真实 GitHub Release 在线升级 E2E 在安装前被契约检查拦截。
- 不修改 Persistent Worker 运行模型，不改 `UpdateIntegrity` 四个哨兵文件，`file_sign` 保持 `8be29c04c34ba1d1cc7ec77d392b2bee`。

## 根因

2026091916 的程序构建、MySQL 5.7、Phase20 integration、压力测试以及 Release Asset 发布均成功；最后的 `e2e-online-upgrade` 在检查 GitHub Release changelog 时要求包含字面量 `更新内容`，而 1916 Release Notes 使用 `修复内容`，因此 E2E 失败。该失败与 worker SQL、数据库迁移或安装程序无关。

## 发布验证

- 正式 Release 继续使用既有 `ZONOE Source Release` workflow。
- PHP 7.0 regression、MySQL 5.7 migration、Phase 20 integration、HTTP load gate 必须成功。
- `package-and-release` 必须成功生成全新的 `source-v2026091917`。
- 最终 `zonoe-online-update.zip` 必须继续包含 `mysql/2026091915_phase20_ipa_worker.sql`。
- `e2e-online-upgrade` 必须通过真实 GitHub Release 路径验证上一正式版本到 `2026091917` 的在线升级。

## 发布后验证

- 不移动、不覆盖 `source-v2026091916` 及更早 Tag/Asset。
- 生产环境升级后确认 `fa_ipa_worker_job` 与 `fa_ipa_worker_state` 存在。
- 创建一次后台扫描/解析任务，确认任务可从 `queued` 被 Worker/FPM fallback 消费并正常完成。
