# Development Changelog

## 2026-09-24 — Release 2026092402

Baseline: `source-v2026092401`.

### Production failures observed

- Real BaoTa click path on 2401 hit `is_file(): open_basedir restriction in effect` when `IpaWorkerLauncher` probed `/www/server/php/70/bin/php` outside the site's allowed paths.
- The failed full-scan request had already created a `pending` Job, so a later incremental scan correctly reported `该数据源已有扫描任务正在运行`; the real defect was the orphan pending lock left after Worker launch failure.
- UI gaps observed: no clear-all scan jobs action; no IPA asset delete/clear action; disabled OpenList sources remained visually mixed with enabled sources; IPA asset origin was not obvious.

### Fix

- `IpaWorkerLauncher` keeps `PHP_BINDIR/php` but removes external `is_file/is_executable` probes, so BaoTa `open_basedir` is not relaxed or bypassed.
- `IpaScanService::createJob()` now preflights/ensures the Worker before inserting a new Job, preventing Worker startup failure from creating an orphan `pending` lock.
- Added `clearScanJobs`, `deleteAsset`, `clearAssets` controller actions and matching UI controls.
- Asset deletion cleans `ipa_parse_attempt`, `ipa_binary`, `ipa_compare_result`, `ipa_category_binding`; OpenList files and `fa_category` remain untouched.
- Asset list now displays OpenList source name/ID; assets originate from OpenList `.ipa` discovery, not from software-source MySQL.
- Disabled OpenList sources are hidden by default with a show/hide toggle; disabled retains configuration and does not mean delete.
- Added idempotent MySQL 5.7 permission migration `release/sql/2026092402_ipa_cleanup_controls.sql`.
- Release commit: `54b15d01d4d848e7243600a145500a865ae6fdfd`.

### Verification and release

- IPA Online Update Release Gate Run `35945370518` — SUCCESS.
- ZONOE Source Release Run `35945370658` — SUCCESS.
- PHP 7.0 regression — SUCCESS.
- MySQL 5.7 migration — SUCCESS.
- Phase 19.3.1 HTTP concurrency/load gate — SUCCESS.
- Package/Release — SUCCESS.
- Real GitHub Release online-update E2E (`source-v2026092401 -> source-v2026092402`) — SUCCESS.
- Release `source-v2026092402` targets `54b15d01d4d848e7243600a145500a865ae6fdfd`.
- Release ZIP size `203900` bytes; SHA256 `5bdd7f86ec0f75c4d259eb31db74b2ffd41f1f14b6460c2d8df588f3d31d0cf2`.
- CI Artifact `zonoe-source-2026092402-online-update`: ID `10786194133`, size `196887` bytes, digest `sha256:51416c88a6191be5ba202e473254a948fc6721c9e2acef6e02cc67ad58e16f3b`.
- Actual BaoTa runtime after installing 2026092402: NOT YET VERIFIED.

## 2026-09-24 — Release 2026092401

- Fixed PHP 7.0 FPM vs shell PHP 8.2 Worker runtime mismatch and FPM secret inheritance.
- Added Scan Worker automatic launcher using `PHP_BINDIR/php` and `PHP_IPA_SERVER_SECRET` inheritance.
- Online Update Gate `35938782889` and Source Release `35938782890` — SUCCESS.
- Real GitHub Release online-update E2E (`2207 -> 2401`) — SUCCESS.
- Production follow-up exposed the `open_basedir` probe issue fixed by 2402.

## 2026-09-24 — Release 2026092207

- Restored full-scan restart semantics while incremental remained mutually exclusive.
- Removed parse quota enforcement and Worker-status/quota UI.
- Fixed framework `HttpResponseException` response-chain handling.
- Clear parse preserves IPA discovery/OpenList/source/`fa_category`.
- Online Update Gate `35929007415` and Source Release `35930081424` — SUCCESS.

## 2026-09-23 — Release 2026092206

- Stable historical regression hotfix. Release commit `2cf0e030eb2db3a4aa079462c7a4399720214d1b`.
- Do not rewrite historical `source-v2026092206`.
