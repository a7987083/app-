# Development Changelog

## 2026-09-20 — 2026091913 release follow-up

### 基线与原因

- `2026091912` 已由 ZONOE Source Release #159 发布。
- #159 的 PHP 7、MySQL 5.7、Phase 20 integration、HTTP load、package-and-release 均通过。
- 最终 `e2e-online-upgrade` 失败，原因是测试要求 Release Notes 精确包含 `更新内容`，而 1912 使用了 `主要更新`。
- 因 1912 已发布，按不可变版本规则不再覆盖，后续修复进入 `2026091913`。

### 实际修改

- `VERSION` / `public/update/ver.txt` / `ver.json` 升级到 `2026091913`。
- `PHASE13_RELEASE.txt` 与 `release/RELEASE_NOTES.md` 更新为 1913。
- `tests/phase20_ui_contract_test.php` 去除固定版本号断言，改为验证统一版本格式。
- Release Notes 固定使用 `## 更新内容` 标题以满足现有 GitHub online-update E2E 契约。
- `ROADMAP.md` / `CHANGELOG_DEV.md` / `HANDOFF.md` / `PROJECT_STATE.json` / `KNOWN_ISSUES.md` 同步维护。

### 验证状态

- 1912 Source Release #159：部分通过，最终 E2E 失败。
- 1913 修改：已完成于工作分支。
- 1913 正式 CI：待正式 release 分支触发。
- 1913 真实服务器更新：未验证。

## 2026-09-20 — 2026091912 release correction

- 原始已安装 `2026091911` HEAD：`edcd4d0c7cf50726ce7d09e3075fe23506ea7fe5`。
- 从原始 1911 到 1912 候选包含 19 commits / 16 changed files。
- 完成 OpenList MySQL 持久化、IPA MySQL source/metadata、引用目录发现/缓存、扫描分页与 Phase 20 在线更新 payload 收口。
- 修正此前错误复用 `2026091911` Release Asset 的版本管理问题，并建立单调递增发布规则。
