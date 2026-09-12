# Development Changelog

## 2026-09-12 — Phase 14.1 Production Hardening

Baseline: `feature/phase13-github-release@d13ccb9ced56ca655a27cf13b5be0d6a33e724e2`
Development branch: `refactor/phase14-production-hardening`

- Fixed multi-package updater atomicity: if a later package fails, `UpdateManager` now restores the current package backup and every previously successful package backup in reverse order.
- Refactored manual update-history rollback and failed-install rollback to share Manager-level backup restoration helpers.
- Hardened `UpdateBackup::rollback()` so failed directory creation, file restore or update-created-file deletion cannot be silently reported as success; database restore is still attempted and incomplete rollback is surfaced explicitly.
- Moved update ZIP/extraction work cache from `public/update/cache` to `runtime/update/cache` so temporary packages and extracted PHP are not under Web Root.
- `UpdateInstaller` now exposes the current backup directory to the Manager for chain-level failure recovery.
- Added `tests/phase14_update_atomicity_test.php` for second-package failure, reverse-order chain rollback, normal file restore/removal, rollback I/O failure and private cache path.
- Added `.github/workflows/phase14_refactor.yml` running PHP 7.0 lint plus the full existing regression suite and the new Phase14 test.
- GitHub Actions Run `34663080449` passed on code commit `5eb58fd53ec1155d688d4f46ece2aac69b286f27`.
- Added `ROADMAP.md` and synchronized Phase14 state/handoff/build/known-issue documentation.

Compatibility: no public `appstore/appstore_v2` protocol changes, no authorization duration/quota changes, no Category schema migration, no Nuosike removal.

## 2026-09-12 — Phase 13.1-13.6 GitHub Release / updater closure

- Added stable GitHub Release publishing for `zonoe-online-update.zip` and mandatory `.sha256`.
- Added real staged online-update progress and Chinese update panel/result details.
- Added update history and manual rollback from successful update records.
- Added updater self-update coverage so UpdateManager/Installer/RuntimeStore and admin update entry can update through the same release package.
- Added local `ver.json.file_sign` synchronization and release-time integrity verification.
- Added real GitHub Release E2E plus rollback runtime regression.
- Stable candidate: version `2026091203`, commit `d13ccb9ced56ca655a27cf13b5be0d6a33e724e2`, Release `source-v2026091203`, CI Run `34654867771` SUCCESS.

## 2026-09-12 — Phase 11 Authorization Operations

- Phase 10 was user-verified and frozen as the production rollback baseline.
- Added configurable self-service transfer budget: total count, daily limit, cooldown and per-IP hourly attempt limit.
- Added `transfer_count` to card records; newly stacked cards inherit the active entitlement chain transfer count.
- Added `/unbind/query` UDID-only remaining-transfer lookup and updated `/unbind` UI with a remaining-count query section.
- Added `fa_card_transfer_log` audit history for successful and failed device transfers.
- Added `fa_authorization_event` history for activation, stacking, transfer and blacklist events.
- Added `/license` public authorization lookup requiring card + UDID.
- Added backend Authorization Center with overview, transfer logs, event logs and system diagnostics.
- Diagnostics cover DB, PHP/extensions, HTTPS/TLS, writable runtime/log/uploads, disk space, backup freshness and optional external encryption endpoint probes.
- Added idempotent runtime schema bootstrap plus `tools/phase11_upgrade.sql` for explicit in-place upgrades.
- Added Phase 11 regression/contract tests and BaoTa deployment artifact workflow.

## 2026-09-11 — Refactor Phase 10

### 10A — source HTTP transport hardening
- Added `SourceHttpClient` for the existing external encryption POST requests.
- TLS peer/hostname verification now defaults on; connect/total timeouts default to 5s/20s.
- cURL/HTTP failures are logged while the existing response envelope is preserved.
- Existing `api.nuosike.com/api.php` and `api.nuosike.com/encrypt.php` endpoints are unchanged.
- `SOURCE_HTTP_VERIFY_TLS=0` is available only as an emergency compatibility rollback until strict-TLS live smoke is complete.

### 10B — semantic source-app fields
- Added `SourceAppRecord` to map semantic names such as `download_url`, `button_color`, `file_size`, and `paid` onto legacy `bt1a/bt1b/bt2a/bt2b` columns.
- `AppStorePayload` and the public source query now use the semantic layer.
- No database column rename or schema migration was introduced.

### 10C — unified source response body
- Added `SourceResponse` for plain output, encrypted wrapper output and final send path.
- Existing plain runtime-field stripping, `@@@ -> \\n`, `appstore`, `appstore_v2` and transport-failure body semantics are covered by tests.
- Legacy header/status behavior is intentionally preserved.

### Stackable authorization
- Added `CardEntitlementPolicy`.
- Every unused card remains one-time activation, but its duration is now added after `max(now, furthest active endtime)` for the same UDID.
- Remaining authorization is never discarded by an early renewal.
- Existing day/week/month/quarter/year durations remain 1/7/30/90/360 days.

### Self-service device replacement
- Added public `/unbind` page.
- Customer provides a previously bound card, old UDID and new UDID.
- Transfer requires the old device to still have active authorization, rejects active blacklist states and an already-active target UDID, and keeps the same final expiration.
- On success the activated-card history was documented as moving to the new UDID. A Phase14 audit found current implementation moves only currently active rows; this semantic mismatch is now tracked and must not be changed without an explicit decision.

### Tests / CI
- Added semantic mapping, response, HTTP/TLS contract, stackable entitlement, device-transfer and controller-architecture tests.
- Existing AppStore golden/equivalence tests were updated to load the semantic helper.
- GitHub Actions Run `34546582035` passed PHP 7.0 / 8.2 / 8.4.

## 2026-09-11 — Refactor Phase 9

### Stable closure
- User reported Phase 8 production/deployment behavior working correctly.
- User completed the production access-log audit for `App-mb.php` and `Index2.php`, then completed server cleanup.
- Both stale duplicate controllers were formally removed from `dev/software-source-v1`.
- CI contracts were inverted to require the retired controllers to remain absent.
- GitHub Actions Run `34545630620` passed PHP 7.0 / 8.2 / 8.4 after the deletion-aware CI update.
- A Phase 9 final BaoTa ZIP was produced as the rollback/stable closure baseline.

## 2026-09-11 — Refactor Phase 8

Baseline: `main@598235962ea328c6558fe4935fe19ba552c1490d`
Development branch: `dev/software-source-v1`

### Category UI follow-up
- Category pagination now uses BootstrapTable `paginationVAlign: 'both'`, so the same navigation/details appear above and below the 1000-row page.
- A successful Category add now closes the add layer without triggering the parent table's automatic refresh. The insert still completes normally; the administrator can refresh manually.
- New App/Category form defaults `是否付费` to `付费`; editing existing rows remains value-driven.

### Category `cs/cstime` root fix
- Removed the historical write-on-read reset from `Category::index()`. Opening the admin list no longer bulk-updates `cs/cstime`.
- Added `CategoryDailyStat` as the only runtime daily counter helper.
- `cstime` now stores integer `YYYYMMDD` instead of day-of-month. This fixes collisions such as 2026-08-11 and 2026-09-11 both previously being `11`.
- Existing legacy `1..31` values require no DB migration: the next hit treats them as an old date and rewrites the row to `YYYYMMDD`.
- First hit on a new date still sets `cs=1`; subsequent same-day hits increment it.
- Counter writes use a transaction and row lock to protect concurrent updates.
- The retained `Index2.php` compatibility path was pointed at the same helper while production access usage was audited; Phase 9 later retired that controller after the production gate passed.

### Card maintenance
- Added `CardCodeGenerator` using `random_bytes(6)` while preserving the visible uppercase prefix + 12 hexadecimal character format.
- Generated batches are unique in memory and checked against existing `fa_kami.kami`; detected collisions are regenerated with bounded retry.
- Card types are restricted to the existing day/week/month/quarter/year IDs and every insert result is checked.
- Inserts remain one row at a time inside the existing transaction; no DB unique-index migration was introduced.
- Blank card `addtime/usetime/endtime` values now normalize to `0` to match the NOT NULL schema.

### Blacklist maintenance
- Manual admin add refuses another active row for the same UDID.
- Expired-only history can be followed by a new active row.
- Existing duplicate history is not destructively deduplicated.
- Expired rows are retained and shown as `已过期`; permanent and unused semantics remain unchanged.

### Legacy controller cleanup gate
- Static route/reference audit found no application use of `App-mb.php` or `Index2.php`.
- Added `tools/legacy_controller_access_audit.sh` and `LEGACY_CONTROLLER_AUDIT.md`; the script refuses deletion when a hit is found or logs are missing.
- The production gate was later completed by the user in Phase 9.

### Tests / CI
- Added/extended Category list UI contract coverage.
- Added `category_daily_stat_test.php` and `category_statistics_contract_test.php`.
- Added `card_code_generator_test.php` and `card_maintenance_contract_test.php`.
- Added `blacklist_maintenance_test.php`.
- Added `legacy_controller_contract_test.php` and `legacy_controller_audit_test.sh`.
- GitHub Actions Run `34542868818` passed the full PHP 7.0 / 8.2 / 8.4 matrix on the Phase 8 code bundle before documentation follow-up commits.

### Compatibility boundary
- `/appstore`, `appstore`, `appstore_v2`, external encryption behavior, source keys, lock semantics, trace semantics and BaoTa DB/rewrite contract were unchanged in Phase 8.

## 2026-09-11 — Refactor Phase 7

- Replaced Category full-list client rendering with true server-side pagination.
- Default page size is 1000, with 200/500/1000 choices.
- Quick search now queries the complete database by application name.
- Type tabs filter on the server and reset page 1.
- `软件说明 / 备注 / 应用图标 / 权重` are hidden by default but remain selectable.
- Category list AJAX no longer builds the complete Tree; parent list is deferred to add/edit.
- List query returns only fields needed by the table and column chooser.
- User later showed the real admin reporting 4713 rows with 1000 rows per page, confirming the paging path was active.

## 2026-09-11 — Refactor Phase 6

- Centralized legacy Base64 `添加者UDID|破解者UDID` parsing in `TraceMonitorPolicy` with the original 25/40-character acceptance rule.
- Clarified monitor labels to `来源身份 / 来源记录次数`.
- Added `tools/trace_monitor_probe.sh`, documentation and regression tests.
- Fresh BaoTa package stopped preloading two inherited 2022 `fa_monitor` observations; existing deployments were not purged.

## 2026-09-11 — Refactor Phase 5

- Fixed blacklist admin false-success caused by missing NOT NULL `usetime/endtime` fields.
- Added `BlacklistPolicy`, complete inserts, optional expiration, first-hit `usetime`, active/expired runtime checks and blacklist persistence tests.
- Monitor-to-blacklist and automatic trace-blacklist paths write complete rows.
- Added root `nginx.rewrite` for BaoTa one-click pseudo-static import.
- User reported Phase 5 deployment/blacklist testing OK.

## 2026-09-11 — Refactor Phase 4

- Fixed BaoTa release packaging after real MySQL 1045 showed the old `user/dbname/pwd` database template had overwritten the correct config.
- Authoritative release DB placeholders are `BT_DB_NAME`, `BT_DB_USERNAME`, `BT_DB_PASSWORD`.
- Added deployment contract tests; real install retest succeeded.
- Static review first identified `App-mb.php` / `Index2.php` as stale duplicate controllers requiring production-log verification before removal.

## 2026-09-11 — Refactor Phase 3

- Added `SourceConfigRepository` with shared 60-second `fa_config` cache and invalidation on config changes.
- Reused the shared config snapshot in AppStore and dylib paths.
- Extended CI to PHP 7.0 / 8.2 / 8.4.

## 2026-09-11 — Refactor Phase 2

- Centralized Category type display/write normalization.
- Made Monitor blacklist moves transactional with missing-row protection.
- Made Kami generation + `fa_kmstr` update transactional and rejected non-positive generation counts.

## 2026-09-11 — Refactor Phase 1

- Extracted `AppStorePayload` mapping from the legacy controller.
- Added legacy-equivalence/payload tests.
- Fixed dylib missing-card null access.
- Removed homepage child-category N+1 queries while retaining external behavior.

## 2026-09-12 — Refactor Phase 12

- Preserved the original Nuosike online updater and added a separate GitHub online-update button beside it.
- Replaced duplicated/unsafe controller update internals with one shared UpdateManager/UpdateInstaller pipeline for both sources.
- Enabled strict update TLS by default with emergency-only `SOURCE_UPDATE_VERIFY_TLS=0`.
- Added mandatory SHA256 for GitHub Release updates, update locking, ZIP traversal/symlink checks and protected-path enforcement.
- Added pre-update database + overwritten-file backups, fail-fast SQL execution, post-copy SHA256 verification and automatic rollback.
- Added Phase 12 update contract tests and fixed project CI to PHP 7.0 only.

## 2026-09-12 — Phase 12.1 hotfix

- Fixed false “website anti-tamper restored files” warning: local integrity is now checked against local `ver.json.file_sign`, not a remote provider signature.
- Fixed Authorization diagnostics on BaoTa/open_basedir by using the site-local updater backup directory and guarded filesystem probes.
- `fa_kami.transfer_count` now means remaining self-service transfer quota. New cards default to 100, admin can edit/top-up it, and each successful transfer consumes one.
- Existing Phase 11/12 used-count values migrate once (`0 -> 100`, `1 -> 99`, etc.) and the schema default becomes 100.
- Stacked active cards share one remaining quota; editing an active card synchronizes the active chain.
