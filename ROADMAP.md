# ZONOE 软件源开发路线图

## 当前阶段

- Repository: `a7987083/app-`
- Active release branch: `release/2026091905-phase20-setting-hotfix`
- Development branch: `dev/2026091914-ipa-workset`
- Last published version: `2026091913`
- Current release target: `2026091914`
- Real server currently verified at: `2026091911`

## 当前目标

- [x] 冻结已发布 `2026091913`，不覆盖历史 Release Asset。
- [x] 严格 MySQL `bt1a` 引用扫描，删除 OpenList full-root fallback。
- [x] 后台解析固定为 1 个，并加入原子领取 / needs_reparse / parse cache。
- [x] active workset 生命周期、历史无引用数据回收、OpenList 停用语义完成。
- [x] 修复 metadata 筛选查询与刷新 stale 状态。
- [x] PR #14 Phase20 预检 CI 全绿。
- [x] 新增 Workset MySQL57 Gate，并在真实 MySQL 5.7 上连续执行 migration 两遍成功。
- [ ] 将 2026091914 最终 HEAD fast-forward 到正式 release 分支。
- [ ] 完整跑通 ZONOE Source Release CI。
- [ ] 验证 `source-v2026091914` 为新 Tag / 新 Asset，而不是覆盖历史版本。
- [ ] 在受控真实服务器执行在线更新并回归 Phase 20 核心功能。

## 发布规则

- 已发布版本不可复用、不可覆盖。
- 任意发布内容变化必须使用下一个单调递增版本号。
- 当前序列：`2026091911 -> 2026091912 -> 2026091913 -> 2026091914 -> ...`。
- CI 通过、Release 发布、真实服务器验证分别记录，不互相替代。

## 当前风险

- Release workflow 仍存在旧 `--clobber` 能力；当前依靠严格升版本避免覆盖历史 Tag。
- 1914 尚未执行正式 Source Release CI。
- 真实服务器尚未验证 1914。

## Next Task

1. 将最终 1914 开发 HEAD 快进到正式 release 分支。
2. 跑完整 ZONOE Source Release CI。
3. 任何失败按日志修复并重新验证；若 Release 尚未创建则继续 1914，若已创建则冻结 1914 并转 1915。
4. 全绿后记录 Run、Commit、Release Tag、Asset SHA256。
5. 最后执行真实服务器升级验证。
