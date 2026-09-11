# Build and Validation

This is a legacy ThinkPHP 5.0.24 / FastAdmin-style application. Validation uses PHP CLI lint, standalone contract tests and a PHP 7.0 / 8.2 / 8.4 GitHub Actions matrix.

## Standard regression suite

```bash
php tests/appstore_payload_test.php
php tests/appstore_equivalence_test.php
php tests/appstore_semantic_equivalence_test.php
php tests/source_app_record_test.php
php tests/source_http_client_contract_test.php
php tests/source_response_test.php
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
php tests/card_entitlement_policy_test.php
php tests/card_device_transfer_contract_test.php
php tests/phase10_controller_contract_test.php
php tests/legacy_controller_contract_test.php
bash tests/legacy_controller_audit_test.sh
php tests/deployment_contract_test.php
```

Also lint all touched application/helper/test PHP files and the shell tools. `.github/workflows/regression.yml` is the authoritative command list.

Validated CI:
- Phase 9 final legacy-controller closure: Run `34545630620`, PHP 7.0 / 8.2 / 8.4 passed.
- Phase 10 transport/semantic/response/stack/transfer code: Run `34546582035`, PHP 7.0 / 8.2 / 8.4 passed.

## Phase 9 stable closure contract

`application/index/controller/App-mb.php` and `application/index/controller/Index2.php` must be absent. Production access-log audit was completed before repository retirement; regression tests now prevent them from returning.

Keep the Phase 9 BaoTa ZIP as the rollback baseline while Phase 10 is live-tested.

## Phase 10A HTTP/TLS smoke

Default behavior:

```text
SOURCE_HTTP_VERIFY_TLS = true
SOURCE_HTTP_CONNECT_TIMEOUT = 5 seconds
SOURCE_HTTP_TIMEOUT = 20 seconds
```

Test both external encryption paths from the actual deployment:

```bash
curl -k -sS -o /tmp/appstore-default.out \
  -w 'HTTP=%{http_code} TTFB=%{time_starttransfer} Total=%{time_total}\n' \
  'https://YOUR-DOMAIN/appstore'

curl -k -sS -H 'APPSTORE: v2' -o /tmp/appstore-v2.out \
  -w 'HTTP=%{http_code} TTFB=%{time_starttransfer} Total=%{time_total}\n' \
  'https://YOUR-DOMAIN/appstore'
```

Use the real client as the authoritative encrypted-protocol smoke because the endpoint may return a valid encrypted wrapper that is not human-readable.

If the server CA/OpenSSL environment rejects the upstream certificate, emergency rollback is:

```bash
export SOURCE_HTTP_VERIFY_TLS=0
```

or equivalent PHP-FPM environment configuration. This is a temporary compatibility fallback, not the preferred final state. Do not change the provider/protocol in the same incident.

## Stackable-card smoke

Use a disposable test UDID and at least three fresh card codes:

1. Activate first card and record its `endtime`.
2. Before it expires, activate the second card on the same UDID.
3. Confirm second card `endtime = previous furthest endtime + second-card duration`.
4. Activate a third card and confirm it extends again from the new furthest endtime.
5. Confirm all three individual cards have `jh=1` and cannot be reused.
6. Confirm `/appstore?udid=...` keeps locked downloads available through the final furthest endtime.

Legacy duration seconds are unchanged: day `86400`, week `604800`, month `2592000`, quarter `7776000`, year `31104000`.

## Self-service `/unbind` smoke

Open:

```text
https://YOUR-DOMAIN/unbind
```

Test:
1. old UDID has active stacked authorization;
2. enter a card previously used by that old UDID + old UDID + unused new UDID;
3. success page reports the unchanged final expiration;
4. old UDID loses the activated-card records and new UDID receives them;
5. new UDID validates normally through `/appstore` / authorization flow;
6. old or new active blacklist rejects transfer;
7. target UDID with an existing active authorization rejects transfer;
8. invalid card/old-UDID pairing rejects transfer.

The proof card can be an older card whose own row has expired, as long as it belongs to the old UDID and the old UDID still has another active stacked entitlement. Successful transfer moves all activated-card history to the new UDID.

## BaoTa one-click release contract

ZIP root must contain:

```text
auto_install.json
import.sql
nginx.rewrite
```

`auto_install.json` must point at `application/database.php` and use run path `/public`. The archived database file must retain:

```text
BT_DB_NAME
BT_DB_USERNAME
BT_DB_PASSWORD
```

Do not restore the historical `user / dbname / pwd` template. Root `nginx.rewrite` remains the ThinkPHP pseudo-static source. Fresh `import.sql` must not preload historical runtime `fa_monitor` observations.

## Phase 10 archive inspection

```bash
unzip -t zonoe-source-phase10-bt.zip
unzip -l zonoe-source-phase10-bt.zip | grep -E 'App-mb.php|Index2.php' && exit 1 || true
unzip -p zonoe-source-phase10-bt.zip application/database.php | grep -E 'BT_DB_NAME|BT_DB_USERNAME|BT_DB_PASSWORD'
unzip -p zonoe-source-phase10-bt.zip application/route.php | grep "Route::rule('unbind'"
unzip -p zonoe-source-phase10-bt.zip application/common/library/SourceHttpClient.php | grep CURLOPT_SSL_VERIFYPEER
unzip -p zonoe-source-phase10-bt.zip application/common/library/SourceAppRecord.php | grep download_url
unzip -p zonoe-source-phase10-bt.zip application/common/library/SourceResponse.php | grep encryptedBody
unzip -p zonoe-source-phase10-bt.zip application/common/library/CardEntitlementPolicy.php | grep stackBaseTime
unzip -p zonoe-source-phase10-bt.zip application/index/view/index/unbind.html | grep '设备自助换绑'
```

## Promotion rule

Phase 8/9 is the confirmed rollback baseline. Do not promote Phase 10 to `main` solely from CI: first complete real `appstore`, `appstore_v2`, stacked activation and `/unbind` smoke tests on a compatible deployment.


## Phase 11 validation

Before production promotion verify:

1. Existing Phase 10 database upgrades without data loss (`AuthorizationSchema` or `tools/phase11_upgrade.sql`).
2. Card list shows `换绑次数`; unused/new cards start at 0.
3. Stack two or more active cards and confirm the new card inherits the current transfer count while expiration continues to stack.
4. `/unbind/query` returns used / max / remaining counts for the current UDID.
5. Each successful `/unbind` consumes exactly one transfer; exhausted budget blocks further transfer.
6. Daily/cooldown/IP abuse controls reject requests without consuming transfer budget.
7. Backend Authorization Center shows transfer audit history and authorization events.
8. `/license` requires card + UDID and reports active/expired status plus remaining transfer budget.
9. System diagnostics reports DB/PHP/extensions/HTTPS/TLS/write paths/disk/backup and optional upstream probes.
10. Plain/appstore/appstore_v2 remain client-compatible.

For an existing Phase 10 database, automatic bootstrap runs on authorization paths. The explicit fallback/manual upgrade is:

```bash
mysql -u <user> -p <database> < tools/phase11_upgrade.sql
```
