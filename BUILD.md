# Build and Validation

This repository is a legacy ThinkPHP 5.0.24 application. Refactor validation uses PHP CLI lint plus standalone regression tests.

## Required PHP checks

```bash
php -l application/common/library/AppStorePayload.php
php -l application/common/library/SourceConfigRepository.php
php -l application/common/library/BlacklistPolicy.php
php -l application/common/model/Config.php
php -l application/index/controller/App.php
php -l application/index/controller/Index.php
php -l application/admin/controller/Category.php
php -l application/admin/controller/Monitor.php
php -l application/admin/controller/Kami.php
php -l application/admin/controller/Black.php
php -l application/admin/model/Black.php
php -l tests/appstore_payload_test.php
php -l tests/appstore_equivalence_test.php
php -l tests/source_config_repository_test.php
php -l tests/blacklist_policy_test.php
php -l tests/blacklist_persistence_contract_test.php
php -l tests/deployment_contract_test.php
```

## Regression tests

```bash
php tests/appstore_payload_test.php
php tests/appstore_equivalence_test.php
php tests/source_config_repository_test.php
php tests/blacklist_policy_test.php
php tests/blacklist_persistence_contract_test.php
php tests/deployment_contract_test.php
```

Expected output includes:

```text
OK appstore_payload_test
OK appstore_equivalence_test
OK source_config_repository_test
OK blacklist_policy_test
OK blacklist_persistence_contract_test
OK deployment_contract_test
```

GitHub Actions runs this matrix on PHP 7.0, 8.2 and 8.4.

## BaoTa one-click release contract

The release ZIP must place all three deployment files at the ZIP root:

```text
auto_install.json
import.sql
nginx.rewrite
```

`auto_install.json` must point `db_config` to `application/database.php` and `run_path` to `/public`.

The release copy of `application/database.php` must come from the current development branch and contain the BaoTa-recognized placeholders:

```text
BT_DB_NAME
BT_DB_USERNAME
BT_DB_PASSWORD
```

Do not copy the historical deployment template over this file. The historical literals `user`, `dbname`, and `pwd` caused a real installation to fail with MySQL error 1045 (`Access denied for user 'dbname'@'localhost'`). Phase 4 real-install retest succeeded after this was corrected.

The root `nginx.rewrite` is the BaoTa one-click pseudo-static source and must contain:

```nginx
location / {

    if (!-e $request_filename){

        rewrite  ^(.*)$  /index.php?s=$1  last;   break;

    }

}
```

Before publishing a ZIP, inspect the actual archive rather than only the working tree:

```bash
unzip -p zonoe-source-phase5-bt.zip application/database.php | grep -E 'BT_DB_NAME|BT_DB_USERNAME|BT_DB_PASSWORD'
unzip -p zonoe-source-phase5-bt.zip application/database.php | grep -E "'user'|'dbname'|'pwd'" && exit 1 || true
unzip -p zonoe-source-phase5-bt.zip nginx.rewrite
unzip -l zonoe-source-phase5-bt.zip | grep -E '(^| )auto_install.json$|(^| )import.sql$|(^| )nginx.rewrite$'
```

## Phase 5 blacklist smoke matrix

1. Admin adds a permanent blacklist row: `fa_black` contains `udid`, current `addtime`, `usetime=0`, `endtime=0`.
2. Admin adds a temporary blacklist row: `endtime` is the chosen Unix timestamp.
3. Blacklist list shows `未使用` before the first hit and `永久` for `endtime=0`.
4. First `/appstore?udid=...` hit returns blacklist payload and stamps `usetime` once.
5. `/dylib?udid=...` with a valid card and active blacklist returns `code=666` and stamps `usetime` if still zero.
6. Expired temporary blacklist no longer blocks `/appstore` or valid `/dylib` validation.
7. Monitor -> blacklist action creates a complete permanent row and removes the monitor row atomically.
8. Automatic trace blacklist creates a complete permanent row and does not delete the monitor record if insert fails.

## Pre-merge live smoke matrix

Use a staging/disposable database and compare external HTTP behavior with baseline commit `598235962ea328c6558fe4935fe19ba552c1490d`.

1. BaoTa one-click install creates/imports database and rewrites all three DB credentials.
2. BaoTa imports root `nginx.rewrite`; `/appstore` works without manually choosing a pseudo-static template.
3. `admin / 123456` login returns non-500 and opens the backend.
4. Plain source, no card.
5. Plain source, valid card.
6. Plain source, expired card.
7. Plain source, active and expired blacklisted UDID.
8. Encrypted default `appstore` wrapper.
9. Encrypted `APPSTORE: v2` wrapper.
10. Free app, `lock=1` app, and nonstandard truthy lock value.
11. Announcement / source metadata and multiline description serialization.

Do not merge to `main` solely from lint/unit results; real BaoTa substitution, rewrite import, external encryption endpoints and production-like database paths require smoke coverage.
