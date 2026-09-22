# Development Changelog

## 2026-09-23 — Release 2026092206

- Baseline: `source-v2026092205`.
- Fixed software-source test-button propagation while retaining `stopImmediatePropagation`; explicit `stopPropagation` was added to satisfy both runtime behavior and CI contract.
- Hardened software-source save/delete paths with defaults, transaction handling and PHP 7.0-compatible Throwable rollback.
- Added orphaned `parsing` task recovery when the Parse Worker is no longer alive.
- Hardened clear-parse-results so orphaned work is reclaimed first while genuine active parsing remains protected.
- No new database migration; 2026092205 schema remains authoritative.
- Fix commit: `f4170135082252482d913f6dcdc4f090c07d8a3e`.
- Release-prep commit: `2cf0e030eb2db3a4aa079462c7a4399720214d1b`.
- IPA gate Run `35782863429` — SUCCESS.
- Formal Release Run `35782863008` — SUCCESS.
- PHP 7.0 regression — SUCCESS.
- MySQL 5.7 migration regression — SUCCESS.
- Phase 19.3.1 HTTP load gate — SUCCESS.
- GitHub Release online-update E2E `2026092205 -> 2026092206` — SUCCESS.
- Release `source-v2026092206` published with `zonoe-online-update.zip` SHA256 `45553d5fd4ef5ef42f097486262ab27d223e166c8df83ffeec5144b03fa45e6f`.
- Production/manual real-device verification remains pending.

## 2026-09-18 — Phase 19.4.2 / Release 2026091807

Historical stable release retained for traceability. See Git history for its full changelog.
