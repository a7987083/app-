# Known Issues and Refactor Backlog

## P0 — do not mix into the current compatibility refactor

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
- Both duplicate stale production logic; no repository references were found during phase-one review.
- Confirm no external/manual routing relies on them, then archive/remove them from runtime controller paths.

### Category list has write-on-read daily reset
- `Category::index()` resets `cs/cstime` by updating rows when the admin list is opened.
- `cstime` uses only day-of-month, which is an unsafe date identity and creates unnecessary table writes.
- Replace with a real date/statistics model or compute daily counts independently.

### Duplicate category-type rules
- Admin controller hardcodes 1..5 labels although `CategoryModel::getTypeList()` is already authoritative.
- Centralize the mapping in the model/config source.

### Monitor blacklist flow needs hardening
- `Monitor::black()` reads raw request input, assumes a monitor row exists, inserts blacklist directly and contains unreachable debug code after success.
- Add validation/idempotence after confirming expected admin behavior.

### Card generation reliability
- `Kami::add()` performs one insert per generated card without a transaction.
- Generator is based on MD5/time/substrings plus `rand()` and has weak collision guarantees.
- Introduce transactional batch generation and uniqueness guarantees as a separately tested behavior change.

### Repeated full config reads
- Public source and dylib endpoints scan all `fa_config` rows then pick a small subset.
- Add a scoped config repository/cache with explicit invalidation from config-save paths.

## P2 — maintainability / modernization

### Cryptic legacy schema fields
`fa_category` exposes legacy names such as `bt1a`, `bt1b`, `bt2a`, `bt2b` throughout controllers/views. Add semantic DTO/accessor names before any physical DB migration.

### Mixed response styles
Controllers mix `echo + die` with ThinkPHP `json()` responses. Standardize only after HTTP golden tests, because headers/body formatting are part of existing client compatibility.

### No repository CI
Add a small workflow for `php -l` and standalone regression tests before expanding the test suite.

### Security headers/cookies
Cookie and transport defaults are legacy. Harden per deployment environment rather than changing defaults blindly.
