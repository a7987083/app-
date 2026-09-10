# Development Changelog

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

### Not changed intentionally
- Stable `main` branch.
- External encryption service URLs/protocol.
- TLS verification behavior.
- Database schema.
- Legacy duplicate controller files pending usage confirmation.
- ThinkPHP/FastAdmin framework version.

### Remaining validation
- Live/staging HTTP smoke tests against a real database.
- External `appstore` and `appstore_v2` encryption smoke tests.
