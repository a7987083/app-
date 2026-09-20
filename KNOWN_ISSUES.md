# Known Issues

## P0 — 已发布版本不可覆盖

### 已确认事实

- 真实服务器曾安装 `2026091910 -> 2026091911`。
- 后续 CI 曾错误复用 `source-v2026091911` 并覆盖 Asset，导致同版本号对应不同内容。
- `source-v2026091912` 与 `source-v2026091913` 均已正式创建并冻结。

### 当前规则

- 已发布版本不可复用、不可覆盖。
- 当前递增序列：`2026091911 -> 2026091912 -> 2026091913 -> 2026091914 -> ...`。
- 任意新的代码、测试、打包或发布内容变化都必须升到下一个版本。
- 若正式 CI 已创建 `source-v2026091914` 后才发现问题，1914 立即冻结，修复进入 1915。

### 剩余风险

Release workflow 当前仍保留 `gh release upload ... --clobber` 的旧路径，因此流程层仍允许人为复用 Tag。当前通过严格升 VERSION 规避；后续应单独修改 workflow，使已存在正式 Tag 直接 fail closed，而不是刷新旧 Asset。

## P1 — 2026091914 正式 Source Release CI 待完成

- PR #14 Phase 20 IPA Management Run #118 已全绿。
- Regression Checks / Phase14 Production Hardening / Phase 17.2 Authorization Integrity 已全绿。
- Phase 20 Workset MySQL57 Run #1 已全绿，最新 Phase20 migrations 在真实 MySQL 5.7 连续执行两遍成功。
- 正式 release 分支尚未快进到最终 1914 HEAD，因此 `source-v2026091914` 尚未创建。

## P1 — Workflow 旧 MySQL57 Gate 覆盖范围不足

旧 `phase20-ipa-management.yml` 和主 Source Release 的 MySQL57 migration 列表停在较早 migration，无法独立证明 `sources_v2 / workset` 新迁移可执行。1914 已新增独立 `Phase 20 Workset MySQL57` workflow 作为补充 Gate；后续可考虑统一迁移清单，减少重复维护。

## P1 — 真实服务器 Phase 20 回归待完成

真实服务器当前最后确认版本仍为 1911。正式 CI 全绿并发布 1914 后，需要受控验证：OpenList 配置、MySQL 软件源、后台 MD5 扫描、强制刷新目录、后台解析 1 个、metadata 筛选、active workset 回收、绑定、治理、更新历史、rollback/reinstall。

## Stable invariants

- 不改写历史 Commit/Tag。
- 不覆盖已发布 Release Asset。
- 不把 CI success 等同于真实生产验证。
- 状态文件必须与实际 branch/version/CI/runtime 同步维护。
