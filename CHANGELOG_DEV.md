# Development Changelog

## 2026-09-20 — 2026091912 release correction and Phase 20 persistence line

### 基线

- Branch: `release/2026091905-phase20-setting-hotfix`.
- Installed historical release `2026091911` original HEAD: `edcd4d0c7cf50726ce7d09e3075fe23506ea7fe5`.
- Pre-1912 candidate HEAD: `5e72bc30b9bb714f69e79597ca3c0d3704cfd346`.
- Diff from original 1911 to candidate: 19 commits / 16 changed files.

### 实际开发内容

- OpenList configuration moved to MySQL durable persistence.
- Added `IpaMysqlSourceService`, `IpaMysqlSource` controller/view/JS and source migration.
- Added `IpaMetadata` controller, `IpaReferenceDiscoveryService`, `IpaDirectoryCache`.
- Updated remote file and scan services, including referenced-directory pagination.
- Updated online-update manifest/builder so Phase 20 source/registry/migration payload is packaged correctly.
- Updated Phase 20 UI/RC/scan contract tests to match current MySQL persistence and packaging semantics.

### CI 修复记录

- `aaa91d1bd879034f304d04874c17f9ca7c0d9b94` — align Phase 20 OpenList contract with MySQL persistence.
- `2451a095d00e395851139f7e1bcb242d3d38eb3f` — make MySQL payload contract whitespace agnostic.
- `5e72bc30b9bb714f69e79597ca3c0d3704cfd346` — fix PHP 7 contract matcher escaping.
- ZONOE Source Release Run #157 / `35477383649`: SUCCESS on the pre-1912 candidate HEAD.

### 发布纠正

- `2026091911` had already been installed on a real server before later candidate changes were built.
- Reusing/clobbering the existing `source-v2026091911` asset was incorrect release-version handling.
- Corrected release target is `2026091912`.
- From 1912 onward, every changed release payload must use the next monotonically increasing version.

### 当前验证状态

- 已修改：是。
- 2026091912 版本提交：进行中。
- 2026091912 CI：待运行。
- 2026091912 真实服务器更新：未验证。
