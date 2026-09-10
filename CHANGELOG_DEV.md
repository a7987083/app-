# Development Changelog

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
- The retained `Index2.php` compatibility path was pointed at the same helper while production access usage is audited.

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
- Static route/reference audit still finds no application use of `App-mb.php` or `Index2.php`.
- Because the requested condition is to confirm production access first, Phase 8 does not delete them without BaoTa access logs.
- Added `tools/legacy_controller_access_audit.sh` and `LEGACY_CONTROLLER_AUDIT.md`. The script refuses deletion when a hit is found or when logs are missing; `--delete` only acts after a zero-hit scan.

### Tests / CI
- Added/extended Category list UI contract coverage.
- Added `category_daily_stat_test.php` and `category_statistics_contract_test.php`.
- Added `card_code_generator_test.php` and `card_maintenance_contract_test.php`.
- Added `blacklist_maintenance_test.php`.
- Added `legacy_controller_contract_test.php` and `legacy_controller_audit_test.sh`.
- GitHub Actions Run `34542868818` passed the full PHP 7.0 / 8.2 / 8.4 matrix on the Phase 8 code bundle before documentation follow-up commits.

### Compatibility boundary
- `/appstore`, `appstore`, `appstore_v2`, external encryption behavior, source keys, lock semantics, trace semantics and BaoTa DB/rewrite contract are unchanged.

## 2026-09-11 — Refactor Phase 7

- Replaced Category full-list client rendering with true server-side pagination.
- Default page size is 1000, with 200/500/1000 choices.
- Quick search now queries the complete database by application name.
- Type tabs filter on the server and reset to page 1.
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
