# Development Changelog

## 2026-09-24 — 2026092207 IPA controls regression fix

Baseline: `release/2026092206-ipa-controls-regression-hotfix` at `cd719391f25713224dab4a1301f9e551b0cde969`.

### Actual code changes

- `8a81c4456d00e20f5fc19d18b680d25a362eba4f` — `IpaOpsSettings::parseQuotaStatus()` no longer gates parsing by window/hour/day counters; only `parse_enabled` can stop new claims.
- `f21ee17da7ddd2634546880ec50c817be0eaab69` — full scan now cancels active same-source scan jobs/items and starts a fresh full job; incremental mode keeps the existing mutual exclusion.
- `6286116d15566e7611d5b86f3a708cbc0ba3b32e` — removes Worker status and parse rate-limit controls/usage from IPA Center UI.
- `51518a17b7338670d63ab68b925caa82c9010904` — moves framework success responses outside targeted broad try/catch blocks so normal `HttpResponseException` flow is not re-caught as an error.

### Verification

- Draft validation PR: `#22`, base `release/2026092206-ipa-controls-regression-hotfix`.
- `IPA Data Center CI` Run `35919441555` — SUCCESS.
  - PHP 7.0 syntax/contracts — SUCCESS.
  - ThinkPHP CLI registration — SUCCESS.
  - iPhoneOS SDK arm64 verifier compile — SUCCESS.
  - OpenList HTTP integration — SUCCESS.
  - MySQL 5.7 schema/claim contention/100k scale — SUCCESS.
  - Scan service safety / operations contracts — SUCCESS.
- `Regression Checks` Run `35919441640` — SUCCESS.
- `Phase14 Production Hardening` Run `35919441698` — SUCCESS.
- Artifact from IPA CI: none (`total_count=0`).
- Production BaoTa/UI/manual verification: NOT YET PERFORMED.
- Real-device verification: NOT APPLICABLE/NOT PERFORMED for this admin-backend change.

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
- GitHub Release online-update E2E `2026092205 -> 2026092206` — SUCCESS.
- Release `source-v2026092206` published with `zonoe-online-update.zip` SHA256 `45553d5fd4ef5ef42f097486262ab27d223e166c8df83ffeec5144b03fa45e6f`.
- Production/manual real-device verification remained pending at release handoff.
