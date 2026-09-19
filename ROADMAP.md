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
- Current development phase: `Phase 20.7.1`
- Verified development HEAD: `21acdf56d9a9a3a6eb32026b54fcdf8312748797`
- Phase 20 CI Run `35409392686`: SUCCESS

## Phase 19.4.2 — 已发布

- [x] 公告只暴露一套授权时间。
- [x] 后台移除全源/部分/验证独立时间按钮。
- [x] `[授权状态]` / `[到期时间]` / `[剩余时间]` 共用同一有效授权选择逻辑。
- [x] 优先级：全软件源 → 指定 App → 仅验证。
- [x] 无有效授权统一显示“已过期或未解锁本源”。
- [x] `[授权摘要]` 收敛为单一时间模型。
- [x] 历史公告变量自动迁移。
- [x] 旧变量运行时兼容并映射到同一时钟。
- [x] 1806 → 1807 在线更新 E2E 通过。

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

- [x] 批量异常选择与去重，单批最多 100 条。
- [x] 批量 preview 使用独立 `batch_hash`，每项继续保留 `plan_hash`。
- [x] apply 前重新生成批量计划，计划漂移则拒绝整个批次。
- [x] 每项继续复用 20.6 单条 apply：幂等、二次验证、审计不降级。
- [x] 批量模式只允许低风险方向：IPA/OpenList 当前数据 → 数据库。
- [x] 批量模式禁止自动移动 OpenList 文件。
- [x] 重复 Bundle ID / 真正 IPA 缺失不自动修复。
- [x] 基于 `ipa_operation_log(state=failed)` 暴露治理失败队列，不新增冗余表。
- [x] 后台治理页增加批量选择、批量预览/执行和失败队列展示。
- [x] Phase 20.7 contract 纳入 GitHub Actions；Run `35409392686` SUCCESS。

### 20.7.2 Retry / Interrupted — 下一阶段

- [ ] 定义失败操作的可重试边界和 retry token。
- [ ] 区分 `failed`、`interrupted`、`unknown`，避免把进程中断误判成业务失败。
- [ ] 启动时/人工触发时识别长期 `running` 操作并转为 interrupted。
- [ ] retry 前重新 preview，并禁止复用过期 plan。
- [ ] retry 继续沿用幂等键和 verify，禁止重复副作用。
- [ ] 后台失败队列增加单条/受控批量重试入口。

### 后续生产增强

- [ ] retention：operation/task/history 保留策略与清理任务。
- [ ] Range metrics：请求数、字节数、命中率、失败率聚合与展示。
- [ ] ignore / ignore_until 的批量与到期恢复管理。
- [ ] 危险操作权限进一步细分。
- [ ] 生产环境 OpenList + 数据库端到端验证。
