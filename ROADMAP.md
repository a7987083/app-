# ZONOE 软件源开发路线图

## 当前阶段

- Repository: `a7987083/app-`
- Branch: `release/2026091905-phase20-setting-hotfix`
- 当前发布目标: `2026091912`
- 历史稳定版本: `2026091911`
- 1911 原始发布 HEAD: `edcd4d0c7cf50726ce7d09e3075fe23506ea7fe5`
- 1912 升版前候选 HEAD: `5e72bc30b9bb714f69e79597ca3c0d3704cfd346`

## Phase 20 当前范围

- [x] OpenList 配置 MySQL 持久化。
- [x] IPA MySQL source/metadata 管理。
- [x] 引用目录发现与目录缓存。
- [x] OpenList 引用目录扫描分页。
- [x] Phase 20 MySQL migration 打包。
- [x] PHP 7 / MySQL 5.7 / OpenList HTTP / rollback CI 修通（候选 HEAD）。
- [ ] 2026091912 升版后的完整 CI 重新验证。
- [ ] 2026091911 -> 2026091912 真实服务器在线更新验证。
- [ ] 真实 OpenList/MySQL 数据上的 Phase 20 功能回归。

## 发布规则

- 已发布并可能被安装的版本号不可复用、不可覆盖。
- 当前从 `2026091912` 开始严格单调递增：1912 → 1913 → 1914 → ...。
- 任意发布内容变化都必须升版本。
- CI 通过不等于真实生产回归通过。

## 阻塞项

- 2026091912 尚未完成本次升版后的完整 Release CI。
- 真实服务器尚未执行 1911 -> 1912。

## Next Task

1. 提交 2026091912 版本元数据与长期项目状态。
2. 运行完整 ZONOE Source Release CI。
3. 若失败，定位第一处真实错误并最小修复后继续 CI。
4. CI 全绿后验证 source-v2026091912 / Release Asset / SHA256。
5. 在真实服务器执行 2026091911 -> 2026091912 并回归 Phase 20 核心功能。
