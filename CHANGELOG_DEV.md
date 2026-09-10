# Development Changelog

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
- External `appstore` and `appstore_v2` encryption smoke tests.
