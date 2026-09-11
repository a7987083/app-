# Known Issues and Refactor Backlog

## P0 — compatibility boundaries

### Legacy framework
- Runtime remains ThinkPHP 5.0.24 / FastAdmin-style.
- Framework upgrade is still deferred until broader HTTP/integration coverage exists.

### External encryption service remains the dominant latency
- Observed plain `/appstore` total was about `0.11s`; encrypted total was about `10.48s`.
- Phase 10A adds bounded timeout/error handling and strict TLS defaults, but does not change the provider or protocol.
- Strict TLS against both real provider endpoints must be smoke-tested before Phase 10 is promoted.
- Emergency rollback only: `SOURCE_HTTP_VERIFY_TLS=0` restores legacy peer-verification-off behavior.

## P1 — still open

### Public self-service transfer abuse controls
- `/unbind` requires card + old UDID + new UDID, validates active entitlement/blacklist/target state, and performs an atomic transfer.
- It does not yet include a dedicated CAPTCHA, per-IP rate limit or persistent transfer-audit table.
- Add these only if public abuse becomes a real operational problem; avoid a schema/security redesign before the initial live workflow is validated.

### Card DB uniqueness is application-enforced
- Card generation uses `random_bytes` and checks candidates against existing `fa_kami.kami`.
- The database still has no unique index on `kami`; historical duplicate data has not been migrated/audited.
- Add a unique index only after a production duplicate audit and migration plan.

### Existing blacklist duplicate history
- Manual admin add refuses another active row for the same UDID.
- Existing historical duplicates are intentionally retained.
- Any active permanent/future row continues to block that UDID.

### Expired blacklist retention
- Expired rows are ignored at runtime and display `已过期` in admin.
- No automatic archive/purge job exists; history is retained deliberately.

## Completed P1 cleanup

### Legacy duplicate controllers
- Production access-log audit was completed by the user.
- `application/index/controller/App-mb.php` and `Index2.php` were removed from the development branch in Phase 9.
- CI now fails if either retired controller returns.

### Category large-list administration
- True server-side pagination, default 1000 rows, full-database app-name search, server type filtering, deferred parent-tree construction and lightweight default columns are active.
- Pagination appears both above and below the list.
- Category add no longer refreshes the entire parent list automatically and new apps default to paid.

### Category daily statistics
- Admin write-on-read reset was removed.
- `CategoryDailyStat` uses `YYYYMMDD` with a transaction + row lock; legacy `1..31` values migrate lazily on next hit.

### Card generation / stacking
- Card generation uses cryptographic random bytes while preserving uppercase prefix + 12-hex format.
- New unused cards can stack after the furthest active expiration without discarding existing remaining time.
- Individual codes remain one-time use.
- Existing day/week/month/quarter/year duration rules are retained.

### Semantic source app fields
- Phase 10B introduces `SourceAppRecord`; runtime source mapping no longer spreads raw `bt1a/bt1b/bt2a/bt2b` knowledge through payload code.
- Physical database fields are unchanged.

### Source response / HTTP transport
- Phase 10A centralizes external POST transport with TLS verification, timeout and error logging.
- Phase 10C centralizes plain/encrypted response-body encoding while preserving the existing public body contract and legacy final-output behavior.

### BaoTa deployment contract
- `BT_DB_NAME`, `BT_DB_USERNAME`, `BT_DB_PASSWORD` placeholders remain authoritative.
- Root `nginx.rewrite` remains required.
- Fresh release SQL does not preload inherited runtime monitor observations.

## P2 — future maintainability

### Framework-to-service separation
Core policies/repositories have been extracted, but controllers still contain orchestration and direct DB access. Continue moving behavior behind service/repository boundaries only when a concrete feature requires it.

### Security headers/cookies
Transport/cookie defaults outside the source encryption HTTP client remain legacy. Harden by deployment context rather than globally changing old framework defaults.

### Physical Category schema migration
Semantic aliases now exist, so a future physical rename of `bt1a/bt1b/bt2a/bt2b` is possible. It is not currently justified because it would require database migration and wider compatibility testing without adding user-visible value.

## Phase 12 online updater

- The GitHub update channel intentionally has no stable package until a stable GitHub Release is published with `zonoe-online-update.zip` and matching `.sha256`; the button should report no stable update in that state.
- Strict update TLS depends on the server CA store. `SOURCE_UPDATE_VERIFY_TLS=0` exists only as an emergency diagnostic/compatibility switch while the CA chain is repaired.
- Database backup/rollback is implemented in PHP for BaoTa compatibility and can take longer on very large databases; do not interrupt an update while the update lock is active.
