# Build and Validation

This repository is a legacy ThinkPHP 5.0.24 application. Refactor validation uses PHP CLI lint plus standalone regression tests.

## Required PHP checks

```bash
php -l application/common/library/AppStorePayload.php
php -l application/common/library/SourceConfigRepository.php
php -l application/common/model/Config.php
php -l application/index/controller/App.php
php -l application/index/controller/Index.php
php -l application/admin/controller/Category.php
php -l application/admin/controller/Monitor.php
php -l application/admin/controller/Kami.php
php -l tests/appstore_payload_test.php
php -l tests/appstore_equivalence_test.php
php -l tests/source_config_repository_test.php
php -l tests/deployment_contract_test.php
```

## Regression tests

```bash
php tests/appstore_payload_test.php
php tests/appstore_equivalence_test.php
php tests/source_config_repository_test.php
php tests/deployment_contract_test.php
```

Expected output includes:

```text
OK appstore_payload_test
OK appstore_equivalence_test
OK source_config_repository_test
OK deployment_contract_test
```

GitHub Actions runs this matrix on PHP 7.0, 8.2 and 8.4.

## BaoTa one-click release contract

The release ZIP must place `auto_install.json` and `import.sql` at the ZIP root. `auto_install.json` must point `db_config` to `application/database.php` and `run_path` to `/public`.

The release copy of `application/database.php` must come from the current development branch and must contain exactly the BaoTa-recognized placeholders:

```text
BT_DB_NAME
BT_DB_USERNAME
BT_DB_PASSWORD
```

Do not copy the historical deployment template over this file. The historical literals `user`, `dbname`, and `pwd` are not valid release placeholders for the current BaoTa one-click import flow and caused a real installation to fail with MySQL error 1045 (`Access denied for user 'dbname'@'localhost'`).

Before publishing a ZIP, inspect its actual archived file rather than only the working tree:

```bash
unzip -p zonoe-source-phase4-bt.zip application/database.php | grep -E 'BT_DB_NAME|BT_DB_USERNAME|BT_DB_PASSWORD'
unzip -p zonoe-source-phase4-bt.zip application/database.php | grep -E "'user'|'dbname'|'pwd'" && exit 1 || true
unzip -l zonoe-source-phase4-bt.zip | grep -E '(^| )auto_install.json$|(^| )import.sql$'
```

## Pre-merge live smoke matrix

Use a staging/disposable database and compare external HTTP behavior with baseline commit `598235962ea328c6558fe4935fe19ba552c1490d`.

1. BaoTa one-click install creates/imports database and rewrites all three DB credentials.
2. `admin / 123456` login returns non-500 and opens the backend.
3. Plain source, no card.
4. Plain source, valid card.
5. Plain source, expired card.
6. Plain source, blacklisted UDID.
7. Encrypted default `appstore` wrapper.
8. Encrypted `APPSTORE: v2` wrapper.
9. Free app, `lock=1` app, and nonstandard truthy lock value.
10. Announcement / source metadata and multiline description serialization.

Do not merge to `main` solely from lint/unit results; real BaoTa substitution, external encryption endpoints and production-like database paths require smoke coverage.
