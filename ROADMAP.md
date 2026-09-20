# ZONOE 软件源开发路线图

## 当前阶段

- Repository: `a7987083/app-`
- Active release branch: `release/2026091905-phase20-setting-hotfix`
- Last published version: `2026091912`
- Current release target: `2026091913`
- Real server currently verified at: `2026091911`

## 当前目标

- [x] 冻结已发布 `2026091912`，不再覆盖。
- [x] 将后续修复顺序提升到 `2026091913`。
- [x] 移除 `phase20_ui_contract_test.php` 对具体版本号的硬编码。
- [x] 统一 1913 Release Notes 标题为 `更新内容`，满足既有 online-update E2E 契约。
- [ ] 完整跑通 ZONOE Source Release CI。
- [ ] 验证 `source-v2026091913` 为新 Tag / 新 Asset，而不是覆盖 1912。
- [ ] 在受控真实服务器执行在线更新并回归 Phase 20 核心功能。

## 发布规则

- 已发布版本不可复用、不可覆盖。
- 任意发布内容变化必须使用下一个单调递增版本号。
- 当前序列：`2026091911 -> 2026091912 -> 2026091913 -> 2026091914 -> ...`。
- CI 通过、Release 发布、真实服务器验证必须分别记录。

## 阻塞项

- `2026091913` 完整 CI 尚未完成。
- Release workflow 仍存在 `--clobber` 能力，流程层硬化尚未落地；当前通过严格升版本规避覆盖旧 Tag。
- 真实服务器尚未验证 1913。

## Next Task

1. 推进 `work/2026091913-release-fix` 到正式 release 分支。
2. 跑完整 ZONOE Source Release CI。
3. 若失败，定位第一处真实错误并最小修复。
4. CI 全绿后记录 Run / Commit / Asset SHA256。
5. 执行真实服务器升级验证并更新状态文件。
