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
Commits:
- `1a0ceed12987e99a3094dfa21fa74a9e86e64c22` — extract pure AppStore payload mapper.
- `3640d1765f68586a32c6cb09a2e147a10ac86771` — payload regression tests.
- `6f7f7d41f59ed0e808e4c1ea8f20ce467f73ecfb` — simplify AppStore controller orchestration.
- `17a390835a990fcffce044536a62f6052ddd782c` — harden dylib missing-card path and remove homepage category N+1 queries.
- `cf38d6d9d895b7e039fb04b3b7cde9dca6736864` — legacy-vs-current AppStore equivalence matrix.

## Validation Completed
- PHP syntax checks passed for the changed PHP source and standalone tests using PHP 8.4.
- `tests/appstore_payload_test.php` passed.
- `tests/appstore_equivalence_test.php` passed.
- The equivalence test compares legacy and refactored source arrays / JSON for guest, expired-card, valid-card, free, paid, odd lock values, source metadata, newline handling and wrapper selection.

## Validation Not Yet Claimed
- No production database fixture is available in the local test container.
- No live-server HTTP integration test has been run yet.
- External encryption services have not been altered; live `appstore` and `appstore_v2` encryption should be smoke-tested before merging to `main`.

## Outstanding Priority Work
### P0 / safety boundary
- Keep `main` unchanged until runtime smoke tests pass.
- Do not combine ThinkPHP framework upgrades, TLS hardening or protocol changes with this refactor.

### P1
- `application/index/controller/App-mb.php` and `Index2.php` are stale duplicate controller files with conflicting class names; confirm no external/manual entry uses them, then remove or archive outside runtime paths.
- `Category.php::index()` performs a table write on a list-page read to reset counters using only day-of-month. Replace with a safer counter model in a separate behavior change.
- Replace hardcoded category type labels in `Category.php` with `CategoryModel::getTypeList()`.
- Harden `Monitor::black()` against missing monitor rows and duplicate blacklist insertions.
- Make card generation transactional / batch-oriented and review randomness/collision guarantees.
- Reduce repeated full-table `fa_config` reads using a scoped config repository/cache with explicit invalidation.

### P2
- Introduce semantic DTO/accessors around legacy fields (`bt1a`, `bt1b`, `bt2a`, `bt2b`) before considering schema renames.
- Standardize controller responses instead of mixing `echo/die` and `return json(...)`, only after golden HTTP tests exist.
- Add CI for PHP lint + standalone regression tests.
- Plan environment-aware cookie/TLS/security hardening separately.

## Stability Rules
1. Preserve existing source JSON keys and existing client semantics.
2. Preserve blacklist, unlock/card and locked-download behavior unless explicitly changing them.
3. Prefer additive changes and small reversible commits.
4. Every protocol-affecting change requires differential/golden tests first.
5. Do not blindly copy historical source versions or diffs over the current baseline.

## Next Recommended Step
Run live smoke tests against a disposable/staging database for plaintext, `appstore`, `appstore_v2`, valid/expired/no-card and blacklisted cases. If all match the baseline externally, continue with the P1 admin/controller cleanup as separate commits.
