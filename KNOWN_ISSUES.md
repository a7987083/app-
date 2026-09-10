# Known Issues and Refactor Backlog

## P0 — do not mix into compatibility refactors

### Legacy framework / dependency boundary
- Runtime is ThinkPHP 5.0.24.
- Framework modernization is desirable but high-risk and must wait until HTTP golden/integration tests exist.

### TLS verification disabled in legacy HTTP clients
- AppStore encryption request currently disables peer verification.
- Other legacy HTTP helpers also disable TLS verification.
- This is a security risk, but changing it can break deployed integrations; harden in a separate tested change.

## P1 — next engineering work

### Stale duplicate controllers
- `application/index/controller/App-mb.php` declares `class App`.
- `application/index/controller/Index2.php` declares `class Index`.
- Both duplicate stale production logic; no repository references were found during review.
- They remain untouched until external/manual URL usage can be ruled out from deployment/access logs.

### Category list has write-on-read daily reset
- `Category::index()` resets `cs/cstime` by updating rows when the admin list is opened.
- `cstime` uses only day-of-month, which is an unsafe date identity and creates unnecessary table writes.
- This behavior was intentionally preserved because removing it changes visible admin statistics.
- Replace with a real date/statistics model or compute daily counts independently in a dedicated behavior-change phase.

### Card generation performance / uniqueness
- Card generation is transactional and rejects non-positive counts.
- Generation still performs one insert per card to preserve current insertion/failure semantics.
- Generator remains based on MD5/time/substrings plus `rand()` and has weak collision guarantees.
- Before switching to batch insert, verify table indexes, maximum generation count, duplicate policy and desired collision behavior.

### Duplicate blacklist policy
- Blacklist insertion is now schema-complete and monitor moves are transactional.
- Historical behavior still permits duplicate `fa_black` rows for the same UDID.
- Phase 5 treats every active row independently: any permanent/future row keeps the UDID blacklisted even if another duplicate row has expired.
- Decide whether blacklist should become idempotent before adding a unique constraint or deduplication.

### Blacklist expiry cleanup
- Phase 5 ignores expired blacklist rows at runtime but does not delete them automatically.
- Decide later whether expired rows should remain as audit history, be archived, or be cleaned periodically.

## Completed P1 cleanup

### Shared config reads
- Phase 3 added `SourceConfigRepository` with a shared 60-second cache and explicit invalidation after config writes/deletes.
- Public AppStore and dylib config reads use the shared repository.

### Category duplicate display/write rules
- Type display uses `CategoryModel::getTypeList()` instead of a second hardcoded 1..5 mapping.
- Add/edit color, description-newline and size normalization share one helper while preserving historical behavior.

### Monitor blacklist safety
- Missing IDs / missing monitor rows are rejected instead of dereferencing null data.
- Insert + delete is transactional.
- Phase 5 now inserts all required `fa_black` fields (`udid/addtime/usetime/endtime`).

### Blacklist false-success bug
- Real testing showed `Black::add()` returned success but inserted nothing because `usetime/endtime` were omitted even though both database columns are `NOT NULL` without defaults.
- Phase 5 adds `BlacklistPolicy`, complete inserts, insert-result checking, optional expiry, first-hit `usetime`, and active/expired evaluation.

### Kami write consistency
- Generation + `fa_kmstr` update is transactional.
- Counts `<= 0` are rejected consistently.
- Empty `fa_kmstr` state no longer causes an array-offset access on the add form.

### BaoTa deployment contract
- Phase 4 fixed `BT_DB_*` credential substitution and the real one-click retest succeeded.
- Phase 5 adds root `nginx.rewrite` so BaoTa can automatically import the required ThinkPHP pseudo-static rule.
- CI validates database placeholders and rewrite contract on PHP 7.0 / 8.2 / 8.4.

## P2 — maintainability / modernization

### Cryptic legacy schema fields
`fa_category` exposes legacy names such as `bt1a`, `bt1b`, `bt2a`, `bt2b` throughout controllers/views. Add semantic DTO/accessor names before any physical DB migration.

### Mixed response styles
Controllers mix `echo + die` with ThinkPHP `json()` responses. Standardize only after HTTP golden tests, because headers/body formatting are part of existing client compatibility.

### Security headers/cookies
Cookie and transport defaults are legacy. Harden per deployment environment rather than changing defaults blindly.
