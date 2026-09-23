# Development Changelog

## 2026-09-24 — Release 2026092207

Baseline: `source-v2026092206` / branch `release/2026092206-ipa-controls-regression-hotfix`.

### Actual code changes

- `8a81c4456d00e20f5fc19d18b680d25a362eba4f` — `IpaOpsSettings::parseQuotaStatus()` no longer gates parsing by window/hour/day counters; only `parse_enabled` can stop new claims.
- `f21ee17da7ddd2634546880ec50c817be0eaab69` — full scan now cancels active same-source scan jobs/items and starts a fresh full job; incremental mode keeps the existing mutual exclusion.
- `6286116d15566e7611d5b86f3a708cbc0ba3b32e` — removes Worker status and parse rate-limit controls/usage from IPA Center UI.
- `51518a17b7338670d63ab68b925caa82c9010904` — moves framework success responses outside targeted broad try/catch blocks so normal `HttpResponseException` flow is not re-caught as an error.
- Release metadata advanced to `2026092207`; existing updater protocol and database migration set were retained.
- Release trigger commit: `49372574305bbf2e52b6b1965e4151a142de8a8a`.

### Verification and release

- Draft validation PR: `#22`, base `release/2026092206-ipa-controls-regression-hotfix`.
- Earlier 2207 business CI passed: IPA Data Center CI, Regression Checks and Phase14 Production Hardening.
- `IPA Online Update Release Gate` Run `35929007415` — SUCCESS.
- `ZONOE Source Release` Run `35930081424` — SUCCESS.
  - PHP 7.0 full regression — SUCCESS.
  - Source integrity manifest — SUCCESS.
  - MySQL 5.7 migrations — SUCCESS.
  - Phase 19.3.1 HTTP concurrency/load gate — SUCCESS.
  - Release metadata validation — SUCCESS.
  - Real online-update ZIP build — SUCCESS.
  - Stable GitHub Release publish/refresh — SUCCESS.
  - GitHub Release online-update E2E (`2206 -> 2207`) — SUCCESS.
- GitHub Release: `source-v2026092207`.
- Release asset `zonoe-online-update.zip`: size `200266` bytes, SHA256 digest `6f828567e3b3d535f8dab60de4fa543d620818b3a1be2189a3f9ef324389f5fc`.
- CI Artifact `zonoe-source-2026092207-online-update`: Artifact ID `10780897151`, size `193451` bytes, artifact digest `sha256:afa9181e40a75cd40dc5ada936a1de9a3e14e71e6214547a4752f6fa0cecb44c`.
- Production BaoTa/UI/manual verification: NOT YET PERFORMED.

## 2026-09-23 — Release 2026092206

- Baseline: `source-v2026092205`.
- Fixed software-source test-button propagation while retaining `stopImmediatePropagation`; explicit `stopPropagation` was added to satisfy both runtime behavior and CI contract.
- Hardened software-source save/delete paths with defaults, transaction handling and PHP 7.0-compatible Throwable rollback.
- Added orphaned `parsing` task recovery when the Parse Worker is no longer alive.
- Hardened clear-parse-results so orphaned work is reclaimed first while genuine active parsing remains protected.
- No new database migration; 2026092205 schema remains authoritative.
- Release-prep commit: `2cf0e030eb2db3a4aa079462c7a4399720214d1b`.
- Formal Release Run `35782863008` — SUCCESS.
- Release `source-v2026092206` published successfully.
