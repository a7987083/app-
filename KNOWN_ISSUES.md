# Known Issues and Refactor Backlog

## P0 — compatibility boundaries

### Legacy framework
- Runtime remains ThinkPHP 5.0.24.
- Framework modernization is high-risk and should wait for broader HTTP integration/golden coverage.

### External encryption / TLS
- Existing `appstore/appstore_v2` whole-payload encryption is intentionally unchanged.
- Real measurement showed plain `/appstore` around 0.11s total and encrypted around 10.48s total; the external encryption call is the dominant latency.
- Legacy HTTP code still disables TLS peer verification. Hardening it may break deployed integrations and belongs in a separate tested phase.

## P1 — still open

### Legacy duplicate controllers require production-log gate
- `application/index/controller/App-mb.php` and `Index2.php` are stale duplicate controllers.
- Static route/reference audit found no application use.
- They remain until actual BaoTa production access logs are checked.
- Phase 8 provides `tools/legacy_controller_access_audit.sh`; it refuses deletion on a hit or when logs are unavailable.

### Existing blacklist duplicate history
- Phase 8 prevents a new manual active duplicate for the same UDID.
- Existing historical duplicates are intentionally not deduplicated destructively.
- Runtime compatibility remains: any active permanent/future row keeps the UDID blocked.

### Card DB uniqueness is enforced in application code, not schema
- Phase 8 replaces the old MD5/time/`rand()` generator with `random_bytes` and checks candidate codes against existing DB codes.
- `fa_kami.kami` still has no unique index. A DB unique constraint should only be added after auditing/migrating possible historical duplicates.
- Card inserts remain one-by-one inside one transaction to preserve current insertion/failure behavior.

### Expired blacklist retention
- Expired rows are ignored at runtime and now display `已过期` in admin.
- They are retained as history; no automatic purge/archive job exists.

## Completed P1 cleanup

### Category large-list administration
- Phase 7 added true server-side pagination with default 1000 rows, full-database app-name search, server-side type tabs, deferred parent tree construction and lightweight default columns.
- Phase 8 adds top+bottom pagination, prevents parent-list auto refresh after a successful add, and defaults new apps to paid.

### Category daily statistics
- Phase 8 removes the admin list write-on-read reset.
- Daily date identity is now `YYYYMMDD` through `CategoryDailyStat`, fixing cross-month same-day collisions.
- Existing `cstime=1..31` values migrate lazily on the next hit.
- Transaction + row lock protects concurrent increments/reset transitions.

### Card generation safety
- Phase 8 uses cryptographic random bytes while preserving uppercase prefix + 12-hex visible format.
- Generated batches are unique, checked against existing DB codes, and insert return values/type values are validated.
- Blank `addtime/usetime/endtime` normalize to `0` to match NOT NULL columns.

### Shared config reads
- Phase 3 added `SourceConfigRepository` with shared 60-second cache and invalidation after config writes/deletes.

### Blacklist persistence / monitor safety
- Phase 5 fixed incomplete `fa_black` inserts, false-success, active/expired evaluation and first-hit use time.
- Monitor -> blacklist remains transactional.
- Phase 8 adds active duplicate prevention for manual admin add and explicit expired-history display.

### BaoTa deployment contract
- Phase 4 fixed `BT_DB_*` credential substitution after a real MySQL 1045 installation failure.
- Phase 5 added root `nginx.rewrite`.
- Fresh release SQL does not preload inherited runtime monitor observations.

## P2 — maintainability

### Cryptic legacy schema fields
`fa_category` still exposes names such as `bt1a`, `bt1b`, `bt2a`, `bt2b`. Introduce semantic accessors/DTO names before considering a physical DB migration.

### Mixed response styles
Controllers mix `echo + die` with ThinkPHP `json()` responses. Standardize only after HTTP behavior is fully golden-tested because headers/body formatting are part of client compatibility.

### Security headers/cookies
Cookie and transport defaults are legacy. Harden per deployment environment rather than changing defaults blindly.
