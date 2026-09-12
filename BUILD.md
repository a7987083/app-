# Build and Validation

## Current baseline

- Stable candidate: `feature/phase13-github-release@d13ccb9ced56ca655a27cf13b5be0d6a33e724e2`
- Version: `2026091203`
- Release CI: `34654867771` — SUCCESS
- Current hardening branch: `refactor/phase14-production-hardening`
- Phase14 code commit before documentation sync: `5eb58fd53ec1155d688d4f46ece2aac69b286f27`
- Phase14 CI: `34663080449` — SUCCESS
- Authoritative compatibility runtime for release/refactor regression: PHP 7.0.

## Standard PHP regression

```bash
set -euo pipefail
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
php tests/authorization_policy_test.php
php tests/phase11_contract_test.php
php tests/phase12_update_contract_test.php
php tests/phase13_update_runtime_test.php
php tests/phase14_update_atomicity_test.php
php tests/legacy_controller_contract_test.php
bash tests/legacy_controller_audit_test.sh
php tests/deployment_contract_test.php
```

Phase14 workflow: `.github/workflows/phase14_refactor.yml`.
Phase13 stable release workflow: `.github/workflows/phase13_github_release.yml`.

## Phase14.1 regression contract

`tests/phase14_update_atomicity_test.php` must keep proving all of the following:

1. Update cache lives in `runtime/update/cache`, never `public/update/cache`.
2. If package 1 succeeds and package 2 fails, Manager-level rollback restores package 2 backup and package 1 backup in reverse order.
3. A normal backup rollback restores overwritten files.
4. Files created only by the failed update are removed.
5. Rollback file I/O failure throws and cannot be reported as successful.

## Phase13/14 online update contract

GitHub stable Release must contain exactly named assets consumed by `GitHubUpdateSource`:

```text
zonoe-online-update.zip
zonoe-online-update.zip.sha256
```

Release must be non-draft and non-prerelease. GitHub update packages require SHA256. Initial and redirected network behavior remains HTTPS/TLS verified by default.

Update pipeline:

```text
UpdateManager lock
  -> UpdateSource package list
  -> UpdateHttpClient download
  -> SHA256
  -> ZIP path/symlink/protected-path validation
  -> runtime/update/cache extraction
  -> UpdateBackup (program + DB + version files)
  -> SQL migration
  -> file copy + SHA256 post-copy verification
  -> SiteConfigSync
  -> write public/update/ver.txt + ver.json
  -> UpdateRuntimeStore status/history
```

On any package-chain failure, all available backups for the current update job must be restored in reverse order.

## Phase14.2 real smoke — required before promotion

Use a disposable BaoTa-compatible deployment with database backup enabled.

### Normal loop

1. Start on `2026091202`.
2. GitHub online update to `2026091203`.
3. Verify:
   - latest/local version
   - release SHA256
   - target files
   - DB migration result
   - `ver.json.file_sign`
   - `public/update/ver.txt`
   - update history
   - `runtime/update_backup/*`
4. Roll back from successful update history to `2026091202`.
5. Verify program files, DB, `ver.json`, `public/update/ver.txt` and integrity.
6. Re-run GitHub update to `2026091203`.

### Failure injection

At minimum test:

- wrong SHA256
- corrupt ZIP
- invalid/protected ZIP path
- SQL execution failure
- target file not writable
- backup directory not writable
- update lock already held
- insufficient disk before/while backup where reproducible
- second package failure after first package succeeds (covered by unit/contract test; add a real staged fixture if available)

The required outcome is never “partially updated but reported failed/successful ambiguously”.

## Public source compatibility smoke

After any production hardening deployment verify with the real client:

- plain `/appstore`
- encrypted default `appstore`
- encrypted `APPSTORE: v2`
- guest locked app behavior
- licensed locked app behavior
- blacklisted UDID response
- activation and stacked authorization

External encryption currently dominates latency; historical measurement was approximately 10.48s encrypted versus 0.11s plain.

## Authorization smoke

- Activate first card, then at least two more before expiry; final expiration must extend from the furthest previous expiration.
- Each individual card remains `jh=1` and cannot be reused.
- `/unbind/query` reports remaining quota.
- Successful transfer decrements remaining quota exactly once.
- daily/cooldown/IP limits reject correctly without unintended quota consumption.
- `/license` requires card + UDID and reports current state.

### Important unresolved transfer-history semantic

Current implementation transfers only currently active `fa_kami` rows to the new UDID. Older documentation said all activated history follows the device. Do not create a smoke expectation for expired rows until the intended stable behavior is explicitly selected.

## BaoTa deployment contract

Package root must retain:

```text
auto_install.json
import.sql
nginx.rewrite
```

`application/database.php` must retain:

```text
BT_DB_NAME
BT_DB_USERNAME
BT_DB_PASSWORD
```

Run path remains `/public`. Fresh SQL must not preload historical runtime monitor observations. `App-mb.php` and `Index2.php` must remain absent.

## Emergency compatibility switches

External source encryption HTTP:

```text
SOURCE_HTTP_VERIFY_TLS=0
```

Online updater HTTP:

```text
SOURCE_UPDATE_VERIFY_TLS=0
```

Both are diagnostic/emergency compatibility fallbacks only. Do not leave TLS disabled as the normal production state.
