# Development Changelog

## 2026-09-11 — Refactor Phase 5

Baseline: `main@598235962ea328c6558fe4935fe19ba552c1490d`
Development branch: `dev/software-source-v1`

### Blacklist fix
- Real admin testing showed `black/add` returned `code=1` while no row appeared in `fa_black`.
- Database inspection confirmed `fa_black.usetime` and `fa_black.endtime` are `NOT NULL` without defaults, while the legacy controller inserted only `udid` and `addtime`.
- Added `application/common/library/BlacklistPolicy.php` as the single source for blacklist insert/lifetime semantics.
- New blacklist rows now persist `udid`, `addtime`, `usetime=0`, and `endtime` (`0` means permanent).
- `Black::add()` validates UDID/expiration, checks the INSERT result, and surfaces database failures instead of returning a false success.
- `Monitor::black()` and automatic trace blacklisting now also write complete rows; monitor move remains transactional and automatic blacklisting keeps insert + monitor cleanup atomic.
- AppStore and dylib blacklist checks now ignore expired blacklist rows.
- The first real blacklist hit records `usetime` when it is still zero.

### Blacklist UI
- Blacklist list now shows add time, use time and expiration time.
- `usetime=0` renders as `未使用`.
- `endtime=0` renders as `永久`.
- Add form supports optional expiration; leaving it blank creates a permanent blacklist.
- Edit form exposes `usetime` and `endtime`; blank values persist as zero to match the existing `NOT NULL` schema.

### BaoTa pseudo-static deployment
- Added root `nginx.rewrite` using the requested ThinkPHP rule:
  `rewrite ^(.*)$ /index.php?s=$1 last; break;`
- BaoTa one-click packages import root `nginx.rewrite` as the Nginx pseudo-static rule.
- `tests/deployment_contract_test.php` now requires the rewrite file and verifies the `location /`, file-existence guard and ThinkPHP target.

### Tests / CI
- Added `tests/blacklist_policy_test.php`.
- Added `tests/blacklist_persistence_contract_test.php` to prevent incomplete `fa_black` inserts from returning.
- CI lints blacklist controller/model/policy code and runs blacklist + deployment contracts on PHP 7.0 / 8.2 / 8.4.

### Phase 4 real-install result
- The user reported the Phase 4 BaoTa one-click install succeeded and the earlier `dbname` credential substitution problem is resolved.

### Still to verify on a real Phase 5 install
- BaoTa automatically imports `nginx.rewrite` into site pseudo-static configuration.
- Admin blacklist add creates a row with `usetime/endtime` populated as designed.
- Permanent blacklist, temporary blacklist expiry, first-hit `usetime`, `/appstore`, and `/dylib` blacklist behavior.

## 2026-09-11 — Refactor Phase 4

Baseline: `main@598235962ea328c6558fe4935fe19ba552c1490d`
Development branch: `dev/software-source-v1`

### Deployment hardening
- Added `tests/deployment_contract_test.php`.
- The deployment contract requires `application/database.php` to contain BaoTa placeholders `BT_DB_NAME`, `BT_DB_USERNAME`, and `BT_DB_PASSWORD`.
- CI rejects the historical release-template literals `user`, `dbname`, and `pwd` in the database config.
- Root `auto_install.json` is checked for `application/database.php`, `/public`, and `admin / 123456` metadata.
- Release documentation now requires inspecting the archived `application/database.php`, not only the repository working tree.

### Real-install finding
- A Phase 3 BaoTa install reached the backend and captcha successfully but login POST returned HTTP 500.
- ThinkPHP runtime log identified MySQL 1045: `Access denied for user 'dbname'@'localhost'`.
- Root cause was the Phase 3 release-building step overlaying the historical database template (`user/dbname/pwd`) over the correct branch version that already used `BT_DB_*` placeholders.
- Phase 4 packaging keeps the current branch `application/database.php` authoritative.

### Legacy controller audit
- `application/route.php` routes `/appstore` to `index/App/list` and `/log` to `index/App/log`.
- No repository reference was found for `App-mb.php` or `Index2.php`.
- Both files declare class names that duplicate the active controllers and contain stale code; `App-mb.php` also contains an invalid one-argument `str_replace()` call.
- They remain in place because repository search cannot disprove direct production URLs or external/manual includes. Production access-log verification is required before removal.

### CI
- PHP 7.0 / 8.2 / 8.4 regression matrix continues to pass.
- Deployment contract is now part of the standard CI test set.

## 2026-09-11 — Refactor Phase 3

Baseline: `main@598235962ea328c6558fe4935fe19ba552c1490d`
Development branch: `dev/software-source-v1`

### Refactored
- Added `application/common/library/SourceConfigRepository.php` with a shared 60-second `fa_config` cache.
- `App.php` now reuses one config snapshot for blacklist switches, encryption mode and source metadata.
- `Index.php::dylibConfig()` now projects its legacy output from the shared repository instead of scanning `fa_config` separately.
- `Config` model invalidates the source-config cache after writes/deletes.

### Tests / compatibility
- Added `tests/source_config_repository_test.php`.
- Extended GitHub Actions to PHP 7.0, 8.2 and 8.4.
- Replaced PHP 7.1 array destructuring in the equivalence test with `list()` so the test suite itself supports PHP 7.0.
- GitHub Actions Run `34523737358` passed all three PHP versions.

### Deployment artifact
- Prepared a Baota one-click deployment ZIP from the stable deployed filesystem baseline plus current refactor runtime files.
- ZIP root contains `auto_install.json` and a full `import.sql` database seed.
- `import.sql` contains FastAdmin/software-source tables plus current category/dylib config additions.
- Initial admin credentials are normalized to `admin / 123456`.
- `application/database.php` in the package is sanitized; no live database password is shipped.

### Still not claimed
- No real Baota one-click installation has been executed from this artifact yet.
- No live/staging HTTP matrix has been run against the deployed Phase 3 package.
- External appstore/appstore_v2 encryption smoke tests remain pending.

## 2026-09-11 — Refactor Phase 2

Baseline: `main@598235962ea328c6558fe4935fe19ba552c1490d`
Development branch: `dev/software-source-v1`

### Refactored
- `Category.php` now uses `CategoryModel::getTypeList()` as the single type-label source instead of duplicating 1..5 labels.
- Category add/edit color, newline and size normalization now share one helper.
- Historical add/edit size conversion differences are intentionally preserved.

### Fixed
- `Monitor::black()` now validates ID/row existence and performs blacklist insert + monitor delete in one transaction.
- Removed unreachable debug output after monitor success response.
- `Kami::add()` now wraps all generated card inserts plus `fa_kmstr` update in one transaction.
- Non-positive generation counts are rejected consistently.
- Empty `fa_kmstr` state no longer causes an array-offset access when opening the add page.

### CI
- Added `.github/workflows/regression.yml`.
- Initial matrix was PHP 8.2 and PHP 8.4; Phase 3 extends it to PHP 7.0 as well.

### Intentionally unchanged
- Stable `main` branch.
- `Category::index()` write-on-read daily counter reset.
- `App-mb.php` and `Index2.php` legacy duplicate controllers.
- Card-generation algorithm and per-row insertion behavior.
- Duplicate blacklist-row policy.
- External encryption protocol and TLS behavior.
- Database schema and ThinkPHP/FastAdmin framework version.

## 2026-09-11 — Refactor Phase 1

Baseline: `main@598235962ea328c6558fe4935fe19ba552c1490d`
Development branch: `dev/software-source-v1`

### Refactored
- Extracted public source protocol mapping into `application/common/library/AppStorePayload.php`.
- Simplified `App.php` from duplicated payload branches into orchestration plus focused helpers.
- Preserved existing source keys, lock/download behavior, newline serialization and appstore/appstore_v2 wrappers.

### Fixed
- `Index::dylib()` no longer reads `$res['udid']` before verifying a card record exists.
- Homepage category loading no longer performs one child query per parent category.

### Tests
- Added `tests/appstore_payload_test.php`.
- Added `tests/appstore_equivalence_test.php` comparing refactored mapping against an explicit legacy implementation.
- PHP 8.4 CLI lint and both standalone tests passed during the refactor session.

### Remaining validation
- Live/staging HTTP smoke tests against a real database.
- External `appstore` / `appstore_v2` encryption smoke tests.
