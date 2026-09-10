# Development Changelog

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
- Matrix: PHP 8.2 and PHP 8.4.
- Lints all PHP files changed by refactor phases 1/2.
- Runs both standalone AppStore regression suites.
- Workflow was configured successfully; a GitHub run had not appeared yet at the time of this update, so CI pass status is not claimed here.

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
