# Development Changelog

## 2026-09-26 — 2026092410 development

Baseline: `source-v2026092409` / `a36b979e86d1b39da6eabf74f5f3e1ac99b3a5a8`.
Development branch: `feature/2026092410-dylib-crud-legacy-codegen`.
Verified checkpoint: `69208ccfd595ccd8cb4aab7093838eb5dc7f4c55`.

### Dylib deletion

- Removed the historical-data deletion block.
- `deleteDylib()` now performs a transaction and deletes:
  - `fa_dylib_version` rows for the Dylib;
  - legacy `fa_dylib_app_binding` rows;
  - `fa_dylib_verify_log` rows matching the Dylib key;
  - the Dylib registration itself.
- UI confirmation now explicitly warns that deletion is destructive and irreversible.

### Version management

- Existing `saveVersion(id)` update path is now exposed by UI editing controls.
- Added `deleteVersion()` POST endpoint.
- Added version-table Edit/Delete actions.
- Edit flow preserves `file_size`, `sha256`, `state`, `offline_grace`, `fail_action`, and `notice`.
- Deleting a version is allowed; clients that still present that version will resolve as `version_unknown`.

### Objective-C code generation

- Generator version advanced to `2.1.0`.
- Existing source-of-truth inputs remain: Dylib registration, selected version, Bootstrap URLs, API endpoints, verification secret.
- Confirmed current `Index::dylib()` and `Index::apiface()` still exist in `application/index/controller/Index.php`.
- Added generated legacy endpoint arrays:
  - `legacy_dylib_urls` -> `/index/index/dylib`
  - `legacy_apiface_urls` -> `/index/index/apiface`
- Generated `*DylibConfig.h/.m` exposes `legacyDylibURLs` / `legacyApiFaceURLs`.
- `GeneratedConfig.json` and `INTEGRATION.md` now document both legacy APIs while retaining the new v2 verification path.

### Verification

- OC Codegen CI #8 / Run `36230462422` — SUCCESS.
- OC Codegen CI #9 / Run `36230506336` — SUCCESS on the new contract coverage.
- PHP 7.0 lint — SUCCESS.
- JavaScript syntax — SUCCESS.
- OC Codegen deterministic contract — SUCCESS.
- Dylib Center UX contract — SUCCESS.
- Online-update package content gate — SUCCESS.
- MySQL 5.7 migration regression — SUCCESS.

### Not yet verified

- No real browser/BaoTa destructive-delete run yet.
- No real browser version-edit/delete run yet.
- No newly generated ZIP has yet been downloaded and integrated into a real Dylib project.
- No `source-v2026092410` release has been published yet.
