# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026091807-unified-announcement-expiry`
- Stable phase/version: `Phase 19.4.2 / 2026091807`
- Release commit: `1ef93306dec0a7b09bb99df55b785511d5b8b4c6`
- Release: `source-v2026091807`

## 当前开发线

- Development branch: `feature/phase20-ipa-management-v1`
- Current development phase: `Phase 20.7 complete in code/CI`
- Verified development HEAD: `438db0823a2d03e220401509ce736da57bc99ab9`
- Phase 20 CI Run `35410113307`: SUCCESS
- Production E2E: NOT VERIFIED

## Phase 20 — IPA Management

- [x] 20.0 UI 基础。
- [x] 20.1 数据模型与基础设施。
- [x] 20.2 OpenList 扫描与增量发现。
- [x] 20.3 IPA Range Parser。
- [x] 20.4 IPA 绑定。
- [x] 20.5 写库模板。
- [x] 20.6 数据治理：detect → preview → apply → verify → audit。

## Phase 20.7 — 生产增强

### 20.7.1 批量治理与失败队列 — 完成

- [x] 单批最多 100 条；批量计划使用 `batch_hash`，单项继续保留 `plan_hash`。
- [x] 批量仅允许低风险方向；禁止批量移动 OpenList 文件。
- [x] 重复 Bundle ID / 真正 IPA 缺失不自动修复。
- [x] 基于 `ipa_operation_log` 暴露治理失败队列。

### 20.7.2 Retry / Interrupted — 完成

- [x] stale `running` → `interrupted`。
- [x] 仅 `failed / interrupted` 可恢复。
- [x] retry 强制重新 preview，禁止复用旧 plan。
- [x] 独立 recovery idempotency；成功后原 operation → `superseded`。

### 20.7.3 Retention + Range metrics — 完成

- [x] Range metrics 聚合实际 parser task `range_bytes / range_requests / reused`。
- [x] 7 / 30 / 90 天窗口；最多聚合最近 5000 条并报告 truncated。
- [x] Retention preview → plan_hash → apply。
- [x] 只清理老的 `success/cancelled` scan history 与 `success/superseded` operation history。
- [x] `running / failed / interrupted / queued / retrying` 不进入 Retention 清理集合。
- [x] 单表单批最多 1000 条。

### 20.7.4 权限与 ignore 生命周期 — 完成

- [x] 批量 ignore / unignore，单批最多 100 条。
- [x] `ignore_until` 到期 sweep 并恢复为 open。
- [x] ignore / unignore / expiry sweep 写入 operation audit。
- [x] 后台展示 ignored / expired 队列。
- [x] 高风险按钮按 FastAdmin `$auth->check()` 隐藏：batch apply、retry、retention apply、ignore lifecycle。
- [x] CI Run `35410113307`: SUCCESS。

## Phase 20 RC 收口 — 下一阶段

- [ ] 在受控生产/预生产环境执行 OpenList + MySQL E2E。
- [ ] 验证 batch governance、OpenList 单条移动、retry/interrupted、ignore expiry、Range metrics、Retention preview/apply。
- [ ] 核对权限组在真实管理员账号上的 UI/API 行为。
- [ ] 确认 Retention 备份/恢复策略后才允许生产 apply。
- [ ] 整理 Phase 20 release candidate、迁移 SQL、在线更新和回滚清单。
