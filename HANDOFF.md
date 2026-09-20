# Software Source Development Handoff

## 当前基线

- Repository: `a7987083/app-`
- Active branch: `release/2026091905-phase20-setting-hotfix`
- Historical installed release: `2026091911`
- Original 1911 release HEAD: `edcd4d0c7cf50726ce7d09e3075fe23506ea7fe5`
- Pre-1912 candidate HEAD: `5e72bc30b9bb714f69e79597ca3c0d3704cfd346`
- Current release target: `2026091912`

## 为什么必须升到 1912

真实服务器已在 2026-09-20 06:17:48（UTC+8）执行 `2026091910 -> 2026091911`。之后开发线继续产生新提交并再次构建了同版本号的 Release Asset。相同版本号对应不同内容会导致服务器只比较 version 时无法发现更新，因此 `2026091911` 必须冻结，后续内容从 `2026091912` 开始。

## 当前关键实现

- OpenList 配置以 MySQL 为 durable persistence。
- `IpaMysqlSourceService` 负责 MySQL source 管理。
- `IpaReferenceDiscoveryService` + `IpaDirectoryCache` 负责引用目录发现和缓存。
- `IpaScanService` 负责扫描，已支持引用目录分页。
- online update builder 负责 program payload + ordered MySQL migration payload。
- Phase 20 保留既有 FastAdmin 路由、Range Parser、绑定、治理、恢复和 Retention 体系。

## 已验证

候选 HEAD `5e72bc30...` 的 ZONOE Source Release #157 已通过：PHP 7 full regression、MySQL 5.7 migration、Phase 19.3.1 HTTP load、Phase 20 integration、package-and-release、real GitHub Release online-update E2E。

注意：这是 1912 升版前候选 HEAD 的 CI 结果。1912 版本提交后必须重新跑完整 CI。

## 发布规则

- 历史 Commit/Tag 不改写。
- 已发布版本不复用、不覆盖 Asset。
- 后续版本严格顺序递增：2026091912、2026091913、2026091914...
- 每次发布都重新执行完整 CI。
- “已提交”“CI 通过”“已发布”“真实服务器验证”必须分开记录。

## 风险

- GitHub 上 `source-v2026091911` 曾发生过 Asset 被后续构建覆盖，不能再把当前 GitHub 1911 Asset 当作服务器 06:17 安装内容的唯一证据。
- 真实服务器 Phase 20 核心业务仍需在 1912 安装后回归。

## 接手步骤

1. `git status`
2. `git branch --show-current`
3. `git rev-parse HEAD`
4. 检查 `PROJECT_STATE.json` 和 `KNOWN_ISSUES.md`。
5. 查看最新 ZONOE Source Release Run。
6. 遇到 CI 错误只修第一处真实错误，不绕过 Gate。
