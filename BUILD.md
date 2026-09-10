# Build and Validation

This repository is a legacy ThinkPHP 5.0.24 application without a root Composer test setup. Phase-one refactor validation therefore uses PHP CLI lint plus standalone pure-logic regression tests.

## Required PHP checks

```bash
php -l application/common/library/AppStorePayload.php
php -l application/index/controller/App.php
php -l application/index/controller/Index.php
php -l tests/appstore_payload_test.php
php -l tests/appstore_equivalence_test.php
```

## Regression tests

```bash
php tests/appstore_payload_test.php
php tests/appstore_equivalence_test.php
```

Expected output:

```text
OK appstore_payload_test
OK appstore_equivalence_test
```

## Pre-merge live smoke matrix

Use a staging/disposable database and compare external HTTP behavior with baseline commit `598235962ea328c6558fe4935fe19ba552c1490d`.

Cases:
1. Plain source, no card.
2. Plain source, valid card.
3. Plain source, expired card.
4. Plain source, blacklisted UDID.
5. Encrypted default `appstore` wrapper.
6. Encrypted `APPSTORE: v2` wrapper.
7. Free app, `lock=1` app, and nonstandard truthy lock value.
8. Announcement / source metadata and multiline description serialization.

Do not merge to `main` solely from lint/unit results; the external encryption endpoints and real database paths require smoke coverage.
