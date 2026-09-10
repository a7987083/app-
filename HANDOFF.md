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
- Source runtime data: `fa_config`, `fa_category`, `fa_kami`, `fa_black`, `fa_monitor`.
- Source protocol mapping: `application/common/library/AppStorePayload.php`.
- Dylib/UDID endpoint and public homepage: `application/index/controller/Index.php`.
- App administration: `application/admin/controller/Category.php` + category views/JS.
- Card / monitor / blacklist administration: `Kami.php`, `Monitor.php`, `Black.php`.
- Site config administration: `application/admin/controller/general/Config.php`.
- `application/extra/site.php` is regenerated from config records and should not be treated as an independent source of truth.

## Source Data Flow
1. Request enters `/appstore`.
2. `App::list()` parses request metadata and legacy trace payload.
3. Blacklist / monitor side effects are applied where configured.
4. Card state determines whether locked download URLs are exposed.
5. `fa_config` and `fa_category` rows are read.
6. `AppStorePayload` maps rows into the legacy public schema.
7. Plain responses strip runtime `UDID` / `Time` fields.
8. Encrypted responses preserve the existing `appstore` / `appstore_v2` wrappers and external encryption endpoints.

## Baseline Protocol Contract
Source keys preserved:
- `name`, `message`, `identifier`, `sourceURL`, `sourceicon`, `payURL`, `unlockURL`, `apps`

App keys preserved:
- `name`, `type`, `version`, `versionDate`, `versionDescription`, `lock`, `downloadURL`, `isLanZouCloud`, `iconURL`, `tintColor`, `size`

Important legacy semantics preserved:
- `APPSTORE: v2` selects `appstore_v2`; every other value selects `appstore`.
- `type=default` is emitted as integer `0`.
- Literal `\\n` in app descriptions keeps the old source JSON newline behavior.
- With an existing card, only `lock === "1"` follows locked-download permission.
- Without a card, legacy PHP truthiness of `lock` is preserved.
- Plain source output removes `UDID` / `Time`; encrypted output retains them before encryption.

## Refactor Phase 1 Completed
- Extracted AppStore payload mapping into `AppStorePayload`.
- Removed duplicated source/config/app mapping from `App.php`.
- Fixed `Index::dylib()` missing-card null access.
- Removed homepage child-category N+1 query pattern.
- Added payload regression and legacy-equivalence tests.

## Refactor Phase 2 Completed (code-level)
- `Category.php` now uses `CategoryModel::getTypeList()` as the authoritative display mapping.
- Category add/edit normalization is centralized while preserving historical size-conversion behavior.
- `Monitor::black()` validates missing rows and wraps blacklist insert + monitor delete in one transaction.
- `Kami::add()` wraps card generation + `fa_kmstr` update in one transaction and rejects counts `<= 0`.
- Added `.github/workflows/regression.yml` for PHP 8.2 / 8.4 syntax checks and AppStore regression suites.

## CI Validation
GitHub Actions run `34511000492` completed successfully:
- `php-regression (8.2)` — success.
- `php-regression (8.4)` — success.
- PHP lint step — success.
- AppStore payload regression step — success.

## Validation Not Yet Claimed
- No live/staging HTTP integration test has been run against a real database.
- External `appstore` / `appstore_v2` encryption services have not been smoke-tested after these refactors.
- Production regression is therefore not claimed yet.

## Intentionally Preserved Risks
- `Category::index()` still performs the historical write-on-read daily reset using day-of-month; changing it would alter visible admin statistics.
- `application/index/controller/App-mb.php` and `Index2.php` remain untouched pending confirmation that deployment/access logs show no external/manual use.
- Kami generation still uses per-row inserts and the legacy MD5/time/rand algorithm; batching/uniqueness changes require schema and failure-policy verification first.
- Monitor blacklist flow still permits duplicate UDID rows by historical behavior.
- TLS verification behavior remains unchanged.

## Stability Rules
1. Keep `main` unchanged until live/staging smoke tests pass.
2. Preserve source JSON keys and existing client semantics.
3. Keep protocol, framework upgrades, TLS hardening and schema migration out of compatibility refactor commits.
4. Prefer small reversible commits.
5. Every protocol-affecting change requires differential/golden tests first.

## Next Recommended Step
Implement a scoped `fa_config` loader/cache with explicit invalidation from config writes, then add a staging HTTP smoke-test matrix for plaintext, `appstore`, `appstore_v2`, no-card, valid-card, expired-card and blacklist cases before considering merge to `main`.
