# Development Changelog

## 2026-09-20 — 2026091914 Phase 20 IPA workset / parser queue

### 基线

- `2026091913` 已完成正式 Source Release CI 并发布，现冻结不可覆盖。
- 真实生产服务器最后确认版本仍为 `2026091911`；1914 生产升级尚未验证。

### 实际修改

- IPA 扫描范围改为启用 MySQL 软件源 `bt1a` 的严格引用工作集，删除 full-root fallback。
- OpenList 保留为文件访问 / MD5 / 目录缓存 / Range provider；普通后台 MD5 扫描使用缓存，强制刷新目录独立执行。
- Parser 固定每次后台解析 1 个，加入原子领取、`needs_reparse` 并发保护、30 分钟失败退避。
- 新增 `fa_ipa_parse_cache`，按 MD5 + size + parser version 复用解析结果，并保留完整 payload。
- `fa_ipa_metadata` 增加 `referenced / needs_reparse`，收敛为 active workset；未引用、未绑定记录支持安全回收。
- 修复 `queued + heartbeat=0` 僵尸任务恢复和后台 CLI spawn 判定。
- 修复 metadata 筛选查询的 ThinkPHP/PDO placeholder 复用问题；刷新失败明确提示旧数据。
- OpenList 停用后扫描、强刷、Parser、连接测试全部 fail closed。
- 新增 `Phase 20 Workset MySQL57` workflow，真实 MySQL 5.7 连续执行最新 Phase20 migration 两遍。

### 预发布验证

- PR #14 `Phase 20 IPA Management` Run #118：contract / integration / mysql57 / package 全绿。
- Regression Checks / Phase14 Production Hardening / Phase 17.2 Authorization Integrity 全绿。
- Phase 20 Workset MySQL57 Run #1：成功。

### 当前状态

- 版本元数据已准备为 `2026091914`。
- 正式 Source Release CI：待 release 分支快进后触发。
- 真实服务器更新：未验证。

## 2026-09-20 — 2026091913 release follow-up

- 1913 修复了 1912 Release Notes 契约问题并完成正式发布。
- `source-v2026091913` 已发布并冻结，不再覆盖。
- Phase 20 UI contract 去除固定版本号依赖。

## 2026-09-20 — 2026091912 release correction

- 原始已安装 `2026091911` HEAD：`edcd4d0c7cf50726ce7d09e3075fe23506ea7fe5`。
- 完成 OpenList MySQL 持久化、IPA MySQL source/metadata、引用目录发现/缓存、扫描分页与 Phase 20 在线更新 payload 收口。
- 建立单调递增、已发布版本不可覆盖规则。
