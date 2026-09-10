# Software Source Development Handoff

## Repository
- Repository: `a7987083/app-`
- Development branch: `dev/software-source-v1`
- Stable baseline branch: `main`
- Stable baseline commit: `598235962ea328c6558fe4935fe19ba552c1490d`
- Baseline source: ceshi1 2026-09-04 import (sanitized DB config)

## Architecture
- Framework: ThinkPHP 5.0.24 / FastAdmin-style application.
- Public source route: `/appstore` -> `application/index/controller/App.php::list()`.
- Runtime tables: `fa_config`, `fa_category`, `fa_kami`, `fa_black`, `fa_monitor`.
- Source protocol mapping: `application/common/library/AppStorePayload.php`.
- Shared source config/cache: `application/common/library/SourceConfigRepository.php`.
- Shared blacklist semantics: `application/common/library/BlacklistPolicy.php`.
- Legacy trace-monitor semantics: `application/common/library/TraceMonitorPolicy.php`.
- Dylib/UDID endpoint and public homepage: `application/index/controller/Index.php`.
- App administration: `application/admin/controller/Category.php` + category views/JS.
- Card / monitor / blacklist administration: `Kami.php`, `Monitor.php`, `Black.php`.
- Site config administration: `application/admin/controller/general/Config.php`.
- `application/extra/site.php` is regenerated from config records and is not an independent source of truth.

## Source Data Flow
1. Request enters `/appstore`.
2. `App::list()` parses request metadata and optional legacy trace payload.
3. `TraceMonitorPolicy` maps Base64 `添加者UDID|破解者UDID` positions to `openblack/openblack2` and accepts only the historical 25/40-character UDID lengths.
4. `SourceConfigRepository` provides one cached config snapshot.
5. Blacklist / monitor side effects are applied where configured.
6. Active blacklist lookup uses `BlacklistPolicy`; `endtime=0` means permanent and expired rows are ignored.
7. Card state determines whether locked download URLs are exposed.
8. `fa_category` rows are read.
9. `AppStorePayload` maps database rows into the legacy public schema.
10. Plain responses strip runtime `UDID` / `Time`; encrypted responses retain the existing `appstore` / `appstore_v2` wrapper behavior.

## Baseline Protocol Contract
Source keys preserved: `name`, `message`, `identifier`, `sourceURL`, `sourceicon`, `payURL`, `unlockURL`, `apps`.

App keys preserved: `name`, `type`, `version`, `versionDate`, `versionDescription`, `lock`, `downloadURL`, `isLanZouCloud`, `iconURL`, `tintColor`, `size`.

Important legacy semantics preserved:
- `APPSTORE: v2` selects `appstore_v2`; every other value selects `appstore`.
- `type=default` is emitted as integer `0`.
- Literal `\\n` in app descriptions keeps the old source JSON newline behavior.
- With an existing card, only `lock === "1"` follows locked-download permission.
- Without a card, legacy PHP truthiness of `lock` is preserved.
- Plain source output removes `UDID` / `Time`; encrypted output retains them before encryption.
- Trace first position remains `添加者/openblack`; second position remains `破解者/openblack2`.
- Trace monitor count is an observation count, not an HTTP error or attack count.

## Refactor Phase 1
- Extracted AppStore payload mapping into `AppStorePayload`.
- Removed duplicated source/config/app mapping from `App.php`.
- Fixed `Index::dylib()` missing-card null access.
- Removed homepage child-category N+1 query pattern.
- Added payload regression and legacy-equivalence tests.

## Refactor Phase 2
- `Category.php` uses `CategoryModel::getTypeList()` as the authoritative display mapping.
- Category add/edit normalization is centralized while preserving historical size behavior.
- `Monitor::black()` validates missing rows and wraps blacklist insert + monitor delete in one transaction.
- `Kami::add()` wraps card generation + `fa_kmstr` update in one transaction and rejects counts `<= 0`.

## Refactor Phase 3
- Added `SourceConfigRepository` with shared 60-second `fa_config` cache.
- `App.php` and `Index.php::dylibConfig()` use the shared config layer.
- Config model writes/deletes invalidate the source-config cache.
- Added `source_config_repository_test.php`.
- CI expanded to PHP 7.0 / 8.2 / 8.4.

## Refactor Phase 4
- A real BaoTa test install exposed MySQL 1045 on backend login: `Access denied for user 'dbname'@'localhost'`.
- Root cause was release packaging, not login logic: the Phase 3 ZIP overlaid the historical `user/dbname/pwd` database template over the branch `application/database.php`.
- The authoritative release database config now uses BaoTa placeholders `BT_DB_NAME`, `BT_DB_USERNAME`, `BT_DB_PASSWORD`.
- Added `tests/deployment_contract_test.php`; CI rejects historical release placeholders and validates `auto_install.json` deployment metadata.
- Phase 4 BaoTa real-install retest was reported successful by the user.
- `App-mb.php` and `Index2.php` were statically audited: no repository route/reference found. They remain until production access logs confirm no direct external use.

## Refactor Phase 5
- Real admin testing showed blacklist add returned `code=1` but no row was inserted.
- Database inspection proved `fa_black.usetime` and `fa_black.endtime` are `NOT NULL` without defaults, while legacy `Black::add()` inserted only `udid/addtime` and never checked `insert()` result.
- Added `BlacklistPolicy` and changed all three blacklist insertion paths (admin add, monitor move, automatic trace blacklist) to persist complete rows.
- `Black::add()` validates input, accepts optional expiration, checks insert result, and reports database failure rather than false success.
- Blacklist list/add/edit UI now includes `usetime/endtime`; `usetime=0` is `未使用`, `endtime=0` is `永久`.
- AppStore and dylib blacklist checks ignore expired rows and record first blacklist hit in `usetime`.
- Added root `nginx.rewrite` with the ThinkPHP rule requested for BaoTa one-click pseudo-static auto import.
- Deployment contract now requires the rewrite file.
- Added blacklist policy and persistence-contract regression tests.
- User subsequently reported Phase 5 deployment and blacklist testing OK.

## Refactor Phase 6
- Confirmed and documented the actual monitor contract: optional `/appstore` JSON `value` is Base64 `添加者UDID|破解者UDID`; the server does not infer those identities itself.
- Added `TraceMonitorPolicy` to centralize payload decoding, first/second position mapping, switch mapping, and the historical 25/40-character acceptance rule.
- `App.php` now uses the helper without changing monitor counting or automatic-blacklist semantics.
- Monitor UI labels now read `来源身份` and `来源记录次数` to avoid implying HTTP failures or intrusion detection.
- Added `tools/trace_monitor_probe.sh` plus `TRACE_MONITOR.md` for reproducible terminal verification.
- Added `trace_monitor_policy_test.php` and `trace_monitor_contract_test.php`.
- Phase 6 fresh BaoTa package removes the two inherited 2022 `fa_monitor` observation rows from `import.sql`; existing deployed databases are not modified.

## CI Validation
Standard matrix covers PHP 7.0 / 8.2 / 8.4 and runs AppStore, config-repository, blacklist, trace-monitor and deployment regression tests.

Phase 6 code CI: GitHub Actions Run `34535349012` passed all three PHP versions before documentation-only follow-up commits.

## Validation Not Yet Claimed
- Phase 6 trace monitor should still be smoke-tested against a disposable/live-compatible deployment with `tools/trace_monitor_probe.sh`.
- External `appstore` / `appstore_v2` encryption services have not been independently smoke-tested after all refactors.
- Production regression is not claimed.

## Intentionally Preserved Risks
- `Category::index()` still performs the historical write-on-read daily reset using day-of-month.
- `App-mb.php` and `Index2.php` remain pending production access-log verification.
- Kami generation keeps the legacy MD5/time/rand algorithm.
- Duplicate blacklist rows remain allowed for compatibility.
- TLS verification behavior remains unchanged.
- Trace `添加者/破解者` identity is based solely on client payload position and is not independently authenticated by the server.

## Stability Rules
1. Keep `main` unchanged until the chosen stable candidate is explicitly promoted.
2. Preserve source JSON keys and existing client semantics.
3. Keep protocol changes, framework upgrades, TLS hardening and schema migration out of compatibility refactor commits.
4. Prefer small reversible commits.
5. Every protocol-affecting change requires differential/golden tests first.
6. BaoTa release ZIP must contain root `auto_install.json`, `import.sql`, and `nginx.rewrite`; archived `application/database.php` must contain only `BT_DB_*` deployment placeholders before installation.
7. Fresh deployment SQL should not preload runtime `fa_monitor` observations.

## Next Recommended Step
Run one Phase 6 trace-monitor smoke test with auto-black disabled and enabled. If that passes, keep Phase 6 as the compatibility baseline and move next to the `Category::index()` `cs/cstime` statistics bug with explicit behavior tests before changing it.
