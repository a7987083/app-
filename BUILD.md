# Build and Validation

This is a legacy ThinkPHP 5.0.24 / FastAdmin-style application. Validation uses PHP CLI lint, standalone contract tests and a PHP 7.0 / 8.2 / 8.4 GitHub Actions matrix.

## Standard regression suite

```bash
php tests/appstore_payload_test.php
php tests/appstore_equivalence_test.php
php tests/source_config_repository_test.php
php tests/blacklist_policy_test.php
php tests/blacklist_persistence_contract_test.php
php tests/blacklist_maintenance_test.php
php tests/trace_monitor_policy_test.php
php tests/trace_monitor_contract_test.php
php tests/category_listing_contract_test.php
php tests/category_daily_stat_test.php
php tests/category_statistics_contract_test.php
php tests/card_code_generator_test.php
php tests/card_maintenance_contract_test.php
php tests/legacy_controller_contract_test.php
bash tests/legacy_controller_audit_test.sh
php tests/deployment_contract_test.php
```

Also lint the application/helper/test PHP files and both shell tools with `php -l` / `bash -n`. `.github/workflows/regression.yml` is the authoritative CI command list.

Phase 8 code CI run `34542868818` passed on PHP 7.0, 8.2 and 8.4.

## Phase 8 admin/statistics/card/blacklist smoke matrix

1. Category list shows pagination controls at both the top and bottom.
2. Default page size is 1000 and 200/500/1000 choices still work.
3. Search still covers the complete database, not only the current page.
4. Category add persists the row, shows success and closes the layer without automatically refreshing the parent 1000-row table.
5. New Category/App form defaults `是否付费` to `付费`; edit preserves the stored value.
6. Opening/refreshing Category admin no longer modifies all `cs/cstime` rows.
7. A category hit on a new date sets `cs=1` and `cstime=YYYYMMDD`; another hit on the same date increments `cs`.
8. Existing legacy `cstime=1..31` rows roll forward lazily on their next hit; no bulk migration is required.
9. Generate test day/week/month/quarter/year cards; generated values retain uppercase prefix + 12 hex characters and insert successfully.
10. Activate a generated card through the existing public flow and verify its duration semantics remain unchanged.
11. Adding an already-active blacklist UDID from admin is rejected; an expired-only historical UDID can be added again.
12. Expired blacklist rows show `已过期` and remain available as history.

## Legacy controller production audit

Static source review is not sufficient to delete `App-mb.php` / `Index2.php`. On the BaoTa server run:

```bash
bash tools/legacy_controller_access_audit.sh /www/wwwlogs
```

Only after a zero-hit result, guarded deletion can be requested:

```bash
bash tools/legacy_controller_access_audit.sh \
  --delete /www/wwwroot/app3.zonoeios.xyz \
  /www/wwwlogs
```

A matching log entry exits 2 and refuses deletion. Missing/unreadable log coverage exits 3 and refuses deletion.

## BaoTa one-click release contract

The ZIP root must contain:

```text
auto_install.json
import.sql
nginx.rewrite
```

`auto_install.json` must use `application/database.php` and run path `/public`. The release copy of `application/database.php` must contain:

```text
BT_DB_NAME
BT_DB_USERNAME
BT_DB_PASSWORD
```

Do not restore the historical `user / dbname / pwd` template. That caused a real MySQL 1045 deployment failure before Phase 4.

The required Nginx rewrite remains:

```nginx
location / {

    if (!-e $request_filename){

        rewrite  ^(.*)$  /index.php?s=$1  last;   break;

    }

}
```

Fresh `import.sql` must not preload the historical 2022 `fa_monitor` runtime observations.

## Release archive inspection

For Phase 8:

```bash
unzip -t zonoe-source-phase8-bt.zip
unzip -p zonoe-source-phase8-bt.zip application/database.php | grep -E 'BT_DB_NAME|BT_DB_USERNAME|BT_DB_PASSWORD'
unzip -p zonoe-source-phase8-bt.zip nginx.rewrite
unzip -p zonoe-source-phase8-bt.zip public/assets/js/backend/category.js | grep "paginationVAlign: 'both'"
unzip -p zonoe-source-phase8-bt.zip application/common/library/CategoryDailyStat.php | grep "date('Ymd'"
unzip -p zonoe-source-phase8-bt.zip application/common/library/CardCodeGenerator.php | grep random_bytes
unzip -p zonoe-source-phase8-bt.zip tools/legacy_controller_access_audit.sh | grep -- '--delete'
```

`App-mb.php` and `Index2.php` are expected to remain in the Phase 8 ZIP until production access logs have been audited.

## Public source compatibility smoke

Before any promotion to `main`, recheck plain guest/valid/expired card, active/expired blacklist, trace monitor, default encrypted `appstore`, `APPSTORE: v2`, announcement/metadata and multiline descriptions. Current external encryption protocol and TLS behavior are intentionally unchanged.

Do not promote solely from lint/unit results; Phase 8 still requires a real BaoTa/admin smoke test.
