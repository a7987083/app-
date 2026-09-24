# Development Changelog

## 2026-09-24 — Release 2026092401

Baseline: `source-v2026092207`.

### Production failure reproduced

- BaoTa site PHP-FPM: PHP 7.0.
- SSH default `/usr/bin/php`: symlink to PHP 8.2, causing old ThinkPHP CLI Fatal (`Array and string offset access syntax with curly braces is no longer supported`).
- Explicit PHP 7.0 CLI then failed because CLI did not inherit FPM's `PHP_IPA_SERVER_SECRET`.
- A newly generated `.env` secret could not decrypt existing `ipa_source.token_ciphertext`, producing `IPA secret integrity check failed`.
- Existing production key was confirmed in PHP 7.0 FPM environment; the defect was Worker process/runtime inheritance, not corrupted OpenList data.

### Fix

- Added `application/common/library/Ipa/IpaWorkerLauncher.php`.
- `IpaCenter::startScan()` now creates the scan job and ensures a Scan Worker is alive.
- Launcher uses `PHP_BINDIR/php`, so the Worker uses the same PHP installation as the web FPM runtime instead of shell `/usr/bin/php`.
- Spawned Worker inherits FPM environment including existing `PHP_IPA_SERVER_SECRET`.
- Added `starting` WorkerState heartbeat before spawn.
- `IpaWorkerLauncher.php` added to `release/online-update-files.txt`.
- Fix commits: `3e19df4411708757ff3839a1659fbbdd4a475015`, `7601ff566a8f14312acc097d98d1c988ec344ead`, `af2930c0152e8880d43dfd620dcd1fa89034ca9e`.
- Release commit: `050dbae659ae48ba2184063361f1b91fb959b6d5`.

### Verification and release

- Candidate PR `#23` remains draft.
- PHP 7.0 compatibility/regression — SUCCESS.
- IPA scan safety, OpenList HTTP, MySQL 5.7 schema/worker claim contention/100k scale — SUCCESS.
- IPA Online Update Release Gate Run `35938782889` — SUCCESS.
- ZONOE Source Release Run `35938782890` — SUCCESS.
- Real GitHub Release online-update E2E (`source-v2026092207 -> source-v2026092401`) — SUCCESS.
- Release `source-v2026092401` targets `050dbae659ae48ba2184063361f1b91fb959b6d5`.
- Release ZIP size `202039` bytes; SHA256 `0e230d19e5803f800482678da4c4de23b17acc2bdaa09625e2e979630e1a4f73`.
- CI Artifact `zonoe-source-2026092401-online-update`: ID `10784416382`, size `194805` bytes, digest `sha256:6cfba91e01f2346ce2f601f540a73678d3209f6bdf3bcd30ab8825bedbdd964d`.
- Actual BaoTa runtime after installing 2026092401: NOT YET VERIFIED.

## 2026-09-24 — Release 2026092207

- Restored full-scan restart semantics while incremental remained mutually exclusive.
- Removed parse quota enforcement and Worker-status/quota UI.
- Fixed framework `HttpResponseException` response-chain handling.
- Clear parse preserves IPA discovery/OpenList/source/`fa_category`.
- Online Update Gate `35929007415` and Source Release `35930081424` — SUCCESS.
- Release `source-v2026092207` published; online-update E2E (`2206 -> 2207`) — SUCCESS.

## 2026-09-23 — Release 2026092206

- Stable historical regression hotfix. Release commit `2cf0e030eb2db3a4aa079462c7a4399720214d1b`.
- Do not rewrite historical `source-v2026092206`.
