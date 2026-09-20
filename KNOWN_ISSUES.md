# Known Issues

## P0 — 已发布版本不可覆盖

### 已确认事实

- 真实服务器曾安装 `2026091910 -> 2026091911`。
- 后续 CI 曾错误复用 `source-v2026091911` 并覆盖 Asset，导致同版本号对应不同内容。
- `source-v2026091912` 也已经正式创建，因此 1912 从发布后起同样必须冻结。

### 当前规则

- 已发布版本不可复用、不可覆盖。
- 当前递增序列：`2026091911 -> 2026091912 -> 2026091913 -> 2026091914 -> ...`。
- 任意新的代码、测试、打包或发布内容变化都必须升到下一个版本。

### 剩余风险

Release workflow 当前仍保留 `gh release upload ... --clobber` 的旧路径，因此流程层仍允许人为复用 Tag。当前通过严格升 VERSION 规避；后续应单独修改 workflow，使已存在正式 Tag 直接 fail closed，而不是刷新旧 Asset。

## P1 — 2026091912 online-update E2E 未通过

- Source Release #159 中 PHP 7、MySQL 5.7、HTTP load、Phase 20 integration、package-and-release 全部通过。
- 最终 `e2e-online-upgrade` 失败：测试要求 changelog 精确包含 `更新内容`，1912 使用的是 `主要更新`。
- 1912 已发布，因此不回写修复；后续修复进入 1913。

## P1 — 2026091913 完整 CI 待验证

- 已将版本提升到 1913。
- 已将 Phase 20 UI contract 改为版本无关格式检查，避免以后每次升版重复失败。
- Release Notes 已统一使用 `更新内容` 标题。
- 正式 release 分支尚待快进并执行完整 CI。

## P1 — 真实服务器 Phase 20 回归待完成

真实服务器当前已确认停留在 1911。CI 全绿并发布 1913 后，需要受控验证：OpenList 配置持久化、测试连接、扫描、metadata、绑定、治理、MySQL migration、更新历史、rollback/reinstall。

## Stable invariants

- 不改写历史 Commit/Tag。
- 不覆盖已发布 Release Asset。
- 不把 CI success 等同于真实生产验证。
- 状态文件必须与实际 branch/version/CI/runtime 同步维护。
