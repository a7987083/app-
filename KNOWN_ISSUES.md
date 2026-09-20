# Known Issues

## P0 — 2026091911 release version was reused after real installation

### 复现/事实

- Real server installed `2026091910 -> 2026091911` successfully at 2026-09-20 06:17:48 UTC+8.
- Original 1911 release HEAD: `edcd4d0c7cf50726ce7d09e3075fe23506ea7fe5`.
- Development continued afterwards and a later CI build reused `source-v2026091911` with updated assets.
- Version-only update detection therefore reports current=latest=1911 even though package content changed.

### 根因

Release workflow allowed an existing tag to be refreshed using `gh release upload ... --clobber`, so one semantic version could map to multiple package contents over time.

### 风险

- Installed servers and current GitHub Release may both report 1911 but contain different files.
- SHA256/history becomes ambiguous if the version number is used as the sole identity.

### 修复状态

- Correct successor version: `2026091912`.
- `2026091911` is frozen as historical installed version and must not be reused again.
- Future releases must be monotonically increasing: 1912 -> 1913 -> 1914 -> ... .
- Release workflow immutability hardening still needs to be verified/landed so an existing production tag cannot be clobbered again.

## P1 — 2026091912 full CI pending

- Pre-version-bump candidate HEAD `5e72bc30...` passed ZONOE Source Release #157.
- The actual 1912 version metadata commit must run the complete CI again.
- Do not call 1912 built/released until that run succeeds.

## P1 — Real Phase 20 production regression pending for 1912

After installing 1912 on a controlled real server, verify OpenList config persistence, connection test, scan, metadata, binding, governance, MySQL migrations, update history and rollback/reinstall behavior.

## Stable invariants

- Do not rewrite historical commits/tags.
- Do not overwrite assets of an already published/installed release version.
- CI success is not equivalent to real production verification.
