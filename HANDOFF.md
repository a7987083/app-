# Software Source Development Handoff

## Repository
- Repository: `a7987083/app-`
- Development branch: `dev/software-source-v1`
- Stable baseline branch: `main`
- Stable baseline commit: `598235962ea328c6558fe4935fe19ba552c1490d`
- Baseline source: ceshi1 2026-09-04 import (sanitized DB config)

## Architecture
- Framework: ThinkPHP 5.0.24 / FastAdmin-style application.
- Public source route: `/appstore` -> `application/index/controller/App.php::list()`.
- Runtime tables: `fa_config`, `fa_category`, `fa_kami`, `fa_black`, `fa_monitor`.
- Source protocol mapping: `application/common/library/AppStorePayload.php`.
- Shared source config/cache: `application/common/library/SourceConfigRepository.php`.
- Dylib/UDID endpoint and public homepage: `application/index/controller/Index.php`.
- App administration: `application/admin/controller/Category.php` + category views/JS.
- Card / monitor / blacklist administration: `Kami.php`, `Monitor.php`, `Black.php`.
- Site config administration: `application/admin/controller/general/Config.php`.
- `application/extra/site.php` is regenerated from config records and is not an independent source of truth.

## Source Data Flow
1. Request enters `/appstore`.
2. `App::list()` parses request metadata and legacy trace payload.
3. `SourceConfigRepository` provides one cached config snapshot.
4. Blacklist / monitor side effects are applied where configured.
5. Card state determines whether locked download URLs are exposed.
6. `fa_category` rows are read.
7. `AppStorePayload` maps database rows into the legacy public schema.
8. Plain responses strip runtime `UDID` / `Time`; encrypted responses retain the existing `appstore` / `appstore_v2` wrapper behavior.

## Baseline Protocol Contract
Source keys preserved: `name`, `message`, `identifier`, `sourceURL`, `sourceicon`, `payURL`, `unlockURL`, `apps`.

App keys preserved: `name`, `type`, `version`, `versionDate`, `versionDescription`, `lock`, `downloadURL`, `isLanZouCloud`, `iconURL`, `tintColor`, `size`.

Important legacy semantics preserved:
- `APPSTORE: v2` selects `appstore_v2`; every other value selects `appstore`.
- `type=default` is emitted as integer `0`.
- Literal `\\n` in app descriptions keeps the old source JSON newline behavior.
- With an existing card, only `lock === "1"` follows locked-download permission.
- Without a card, legacy PHP truthiness of `lock` is preserved.
- Plain source output removes `UDID` / `Time`; encrypted output retains them before encryption.

## Refactor Phase 1
- Extracted AppStore payload mapping into `AppStorePayload`.
- Removed duplicated source/config/app mapping from `App.php`.
- Fixed `Index::dylib()` missing-card null access.
- Removed homepage child-category N+1 query pattern.
- Added payload regression and legacy-equivalence tests.

## Refactor Phase 2
- `Category.php` uses `CategoryModel::getTypeList()` as the authoritative display mapping.
- Category add/edit normalization is centralized while preserving historical size behavior.
- `Monitor::black()` validates missing rows and wraps blacklist insert + monitor delete in one transaction.
- `Kami::add()` wraps card generation + `fa_kmstr` update in one transaction and rejects counts `<= 0`.

## Refactor Phase 3
- Added `SourceConfigRepository` with shared 60-second `fa_config` cache.
- `App.php` and `Index.php::dylibConfig()` use the shared config layer.
- Config model writes/deletes invalidate the source-config cache.
- Added `source_config_repository_test.php`.
- CI expanded to PHP 7.0 / 8.2 / 8.4.

## Refactor Phase 4
- A real BaoTa test install exposed MySQL 1045 on backend login: `Access denied for user 'dbname'@'localhost'`.
- Root cause was release packaging, not login logic: the Phase 3 ZIP overlaid the historical `user/dbname/pwd` database template over the branch `application/database.php`.
- The authoritative release database config now uses BaoTa placeholders `BT_DB_NAME`, `BT_DB_USERNAME`, `BT_DB_PASSWORD`.
- Added `tests/deployment_contract_test.php`; CI rejects historical release placeholders and validates `auto_install.json` deployment metadata.
- Release verification must inspect the actual ZIP copy of `application/database.php` before delivery.
- `App-mb.php` and `Index2.php` were statically audited: no repository route/reference found. They remain until production access logs confirm no direct external use.

## CI Validation
Latest Phase 4 workflow run `34526778715` completed successfully. Standard matrix covers PHP 7.0 / 8.2 / 8.4 and runs AppStore, config-repository and deployment-contract regression tests.

## Validation Not Yet Claimed
- Phase 4 BaoTa ZIP still requires a fresh disposable-site install test.
- External `appstore` / `appstore_v2` encryption services have not been smoke-tested after refactors.
- Production regression is not claimed.

## Intentionally Preserved Risks
- `Category::index()` still performs the historical write-on-read daily reset using day-of-month.
- `App-mb.php` and `Index2.php` remain pending production access-log verification.
- Kami generation keeps the legacy MD5/time/rand algorithm.
- Monitor blacklist flow keeps historical duplicate-row behavior.
- TLS verification behavior remains unchanged.

## Stability Rules
1. Keep `main` unchanged until live/staging smoke tests pass.
2. Preserve source JSON keys and existing client semantics.
3. Keep protocol changes, framework upgrades, TLS hardening and schema migration out of compatibility refactor commits.
4. Prefer small reversible commits.
5. Every protocol-affecting change requires differential/golden tests first.
6. BaoTa release ZIP must contain root `auto_install.json` + `import.sql`; its archived database config must contain only `BT_DB_*` deployment placeholders before installation.

## Next Recommended Step
Install the Phase 4 BaoTa package on a disposable test site. Confirm BaoTa rewrites all three database placeholders, `admin / 123456` login succeeds without HTTP 500, then run plaintext / `appstore` / `appstore_v2` plus valid-card / expired-card / blacklist HTTP smoke tests before any merge to `main`.
