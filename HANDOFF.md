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
- Shared blacklist semantics: `application/common/library/BlacklistPolicy.php`.
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
5. Active blacklist lookup uses `BlacklistPolicy`; `endtime=0` means permanent and expired rows are ignored.
6. Card state determines whether locked download URLs are exposed.
7. `fa_category` rows are read.
8. `AppStorePayload` maps database rows into the legacy public schema.
9. Plain responses strip runtime `UDID` / `Time`; encrypted responses retain the existing `appstore` / `appstore_v2` wrapper behavior.

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
- Phase 4 BaoTa real-install retest was reported successful by the user.
- `App-mb.php` and `Index2.php` were statically audited: no repository route/reference found. They remain until production access logs confirm no direct external use.

## Refactor Phase 5
- Real admin testing showed blacklist add returned `code=1` but no row was inserted.
- Database inspection proved `fa_black.usetime` and `fa_black.endtime` are `NOT NULL` without defaults, while legacy `Black::add()` inserted only `udid/addtime` and never checked `insert()` result.
- Added `BlacklistPolicy` and changed all three blacklist insertion paths (admin add, monitor move, automatic trace blacklist) to persist complete rows.
- `Black::add()` validates input, accepts optional expiration, checks insert result, and reports database failure rather than false success.
- Blacklist list/add/edit UI now includes `usetime/endtime`; `usetime=0` is `未使用`, `endtime=0` is `永久`.
- AppStore and dylib blacklist checks ignore expired rows and record first blacklist hit in `usetime`.
- Added root `nginx.rewrite` with the ThinkPHP rule requested for BaoTa one-click pseudo-static auto import.
- Deployment contract now requires the rewrite file.
- Added blacklist policy and persistence-contract regression tests.

## CI Validation
Standard matrix covers PHP 7.0 / 8.2 / 8.4 and runs AppStore, config-repository, blacklist and deployment regression tests. Final Phase 5 run must pass before release ZIP delivery.

## Validation Not Yet Claimed
- Phase 5 BaoTa ZIP still requires one real disposable-site install.
- Verify BaoTa imported `nginx.rewrite` automatically.
- Verify admin blacklist add creates `fa_black` row with `usetime=0` and expected `endtime`.
- Verify first `/appstore` or valid `/dylib` blacklist hit stamps `usetime`, permanent blacklist blocks, and expired temporary blacklist no longer blocks.
- External `appstore` / `appstore_v2` encryption services have not been smoke-tested after refactors.
- Production regression is not claimed.

## Intentionally Preserved Risks
- `Category::index()` still performs the historical write-on-read daily reset using day-of-month.
- `App-mb.php` and `Index2.php` remain pending production access-log verification.
- Kami generation keeps the legacy MD5/time/rand algorithm.
- Duplicate blacklist rows remain allowed for compatibility.
- TLS verification behavior remains unchanged.

## Stability Rules
1. Keep `main` unchanged until live/staging smoke tests pass.
2. Preserve source JSON keys and existing client semantics.
3. Keep protocol changes, framework upgrades, TLS hardening and schema migration out of compatibility refactor commits.
4. Prefer small reversible commits.
5. Every protocol-affecting change requires differential/golden tests first.
6. BaoTa release ZIP must contain root `auto_install.json`, `import.sql`, and `nginx.rewrite`; archived `application/database.php` must contain only `BT_DB_*` deployment placeholders before installation.

## Next Recommended Step
Install the Phase 5 BaoTa package on a disposable site. Confirm database credential substitution and admin login still work, verify pseudo-static rule import, then test permanent/temporary blacklist creation and `/appstore` / `/dylib` enforcement. If these pass, Phase 5 is a suitable new stable candidate.
