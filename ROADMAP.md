# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026091807-unified-announcement-expiry`
- Stable phase/version: `Phase 19.4.2 / 2026091807`
- Release commit: `1ef93306dec0a7b09bb99df55b785511d5b8b4c6`
- Release: `source-v2026091807`
- Unified-announcement CI Run `35306216020`: SUCCESS
- Formal Release Run `35306308086`: SUCCESS
- Online-update E2E `2026091806 -> 2026091807`: SUCCESS

## 当前开发线

- Development branch: `feature/phase20-ipa-management-v1`
- Current development phase: `Phase 20.7.3`
- Verified development HEAD: `a573d6f2945edcd0ca7c44a2002f1212b4a19bb2`
- Phase 20 CI Run `35409913235`: SUCCESS

## Phase 20 — IPA Management

- [x] 20.0 UI 基础。
- [x] 20.1 数据模型与基础设施。
- [x] 20.2 OpenList 扫描与增量发现。
- [x] 20.3 IPA Range Parser。
- [x] 20.4 IPA 绑定。
- [x] 20.5 写库模板。
- [x] 20.6 数据治理：detect → preview → apply → verify → audit。

## Phase 20.7 — 生产增强

### 20.7.1 批量治理与失败队列 — 已完成开发/CI

- [x] 单批最多 100 条；批量计划使用 `batch_hash`，单项继续保留 `plan_hash`。
- [x] 批量仅允许低风险方向；禁止批量移动 OpenList 文件。
- [x] 重复 Bundle ID / 真正 IPA 缺失不自动修复。
- [x] 基于 `ipa_operation_log` 暴露治理失败队列，不新增冗余表。
- [x] 后台治理页增加批量选择、预览/执行和失败队列。

### 20.7.2 Retry / Interrupted — 已完成开发/CI

- [x] 长期 `running` 可按 heartbeat/updatetime 标记为 `interrupted`。
- [x] 仅 `failed / interrupted` 允许进入恢复流程。
- [x] retry 前重新 preview，禁止复用旧 plan。
- [x] retry 使用独立幂等键，避免撞原 `uniq_idempotency`。
- [x] 恢复成功后新 operation=`success`，旧 operation=`superseded` 并记录 `recovered_by`。
- [x] 后台恢复队列提供扫描中断与重新预览后重试。
- [x] CI Run `35409559363`: SUCCESS。

### 20.7.3 Retention + Range metrics — 已完成开发/CI

- [x] Range metrics 直接聚合 parser task item 的实际 `range_bytes / range_requests / reused`。
- [x] 支持 7 / 30 / 90 天窗口；单次聚合最多读取最近 5000 条并显式标记 truncated。
- [x] 后台展示解析/复用、Range 字节、请求数和平均每请求字节数。
- [x] Retention 默认 90 天，最小 7 天，最大 3650 天。
- [x] Retention 固定 preview → plan_hash → apply，计划漂移拒绝执行。
- [x] 只清理老的 success/cancelled scan task/items 与 success/superseded operation log。
- [x] `running / failed / interrupted / queued / retrying` 明确保护，不进入清理集合。
- [x] 单表单次最多清理 1000 条，避免一次性大事务。
- [x] CI Run `35409913235`: SUCCESS。

### 20.7.4 权限与治理生产收尾 — 下一阶段

- [ ] ignore / ignore_until 批量管理与到期恢复视图。
- [ ] 高风险操作权限进一步细分并做 UI 能力隐藏/禁用。
- [ ] Retention / recovery / batch 操作补充生产审计摘要。
- [ ] 生产 OpenList + MySQL 端到端验证。
- [ ] 形成 Phase 20 release candidate / 发布清单。
