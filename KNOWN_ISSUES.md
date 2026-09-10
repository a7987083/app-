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
- This behavior was intentionally preserved in phase two because removing it changes visible admin statistics.
- Replace with a real date/statistics model or compute daily counts independently in a dedicated behavior-change phase.

### Repeated full config reads
- Public source and dylib endpoints scan all `fa_config` rows then pick a small subset.
- Add a scoped config repository/cache with explicit invalidation from config-save paths.

### Card generation performance / uniqueness
- Phase two made card generation transactional and corrected non-positive count validation.
- Generation still performs one insert per card to preserve current insertion/failure semantics.
- Generator remains based on MD5/time/substrings plus `rand()` and has weak collision guarantees.
- Before switching to batch insert, verify table indexes, maximum generation count, duplicate policy and desired collision behavior.

### Monitor blacklist idempotence
- Phase two now validates the monitor row and wraps blacklist insert + monitor delete in one transaction.
- Historical behavior still permits duplicate `fa_black` rows when the same UDID is blacklisted from different monitor records.
- Decide whether blacklist should become idempotent before adding a unique constraint or deduplication.

## Completed P1 cleanup

### Category duplicate display/write rules
- Type display now uses `CategoryModel::getTypeList()` instead of a second hardcoded 1..5 mapping.
- Add/edit color, description-newline and size normalization now share one helper while preserving the historical add/edit size-conversion difference.

### Monitor blacklist safety
- Missing IDs / missing monitor rows are rejected instead of dereferencing null data.
- Insert + delete is transactional.
- Unreachable debug output after success was removed.

### Kami write consistency
- Generation + `fa_kmstr` update is now transactional.
- Counts `<= 0` are rejected consistently with the existing error text.
- Empty `fa_kmstr` state no longer causes an array-offset access on the add form.

### Repository CI
- `.github/workflows/regression.yml` now defines PHP 8.2/8.4 syntax checks and standalone AppStore regression tests.
- At creation time GitHub had not yet exposed a workflow run, so CI success is not yet claimed.

## P2 — maintainability / modernization

### Cryptic legacy schema fields
`fa_category` exposes legacy names such as `bt1a`, `bt1b`, `bt2a`, `bt2b` throughout controllers/views. Add semantic DTO/accessor names before any physical DB migration.

### Mixed response styles
Controllers mix `echo + die` with ThinkPHP `json()` responses. Standardize only after HTTP golden tests, because headers/body formatting are part of existing client compatibility.

### Security headers/cookies
Cookie and transport defaults are legacy. Harden per deployment environment rather than changing defaults blindly.
