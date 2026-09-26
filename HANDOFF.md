# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Stable release: `source-v2026092409`
- Stable branch: `release/2026092409-dylib-center-ux-simplification`
- Stable commit: `a36b979e86d1b39da6eabf74f5f3e1ac99b3a5a8`
- Development branch: `feature/2026092410-dylib-crud-legacy-codegen`
- Verified development checkpoint: `69208ccfd595ccd8cb4aab7093838eb5dc7f4c55`
- OC Codegen CI #9 / Run `36230506336`: SUCCESS.

## 2410 implementation

1. `DylibCenter::deleteDylib()` no longer blocks deletion when versions, legacy bindings, or verify logs exist.
2. Dylib deletion is transactional and cascades its versions, legacy `dylib_app_binding` rows, matching verify logs, then the Dylib registration.
3. Version table now has Edit/Delete controls. Editing uses the existing `saveVersion(id)` path; deleting uses new `deleteVersion()`.
4. Version edit preserves `file_size`, SHA256, state, offline grace, fail action, and notice.
5. Objective-C generator advanced to 2.1.0 and still derives config from the selected Dylib/version/runtime config/verify secret.
6. Historical public APIs are not reimplemented: current `Index::dylib()` and `Index::apiface()` remain the source of truth.
7. Codegen now exposes legacy URL arrays based on configured API endpoints:
   - `/index/index/dylib`
   - `/index/index/apiface`
8. Generated Config header/implementation, `GeneratedConfig.json`, and `INTEGRATION.md` expose/document the legacy endpoints alongside the new verification client.

## Verified

- PHP 7.0 syntax and contracts: passed.
- Dylib Center JS syntax: passed.
- OC Codegen deterministic contract: passed.
- Legacy endpoint codegen assertions: passed.
- Dylib CRUD/version CRUD contract assertions: passed.
- Dylib Center UX contract: passed.
- MySQL 5.7 migration regression: passed.
- Online-update package content gate: passed.

## Not yet verified

- Real BaoTa/browser deletion of a Dylib that already has history.
- Real browser version edit/delete behavior.
- Real generated ZIP integration in a Dylib project.
- Formal 2410 release gate / online-update E2E / published release.

## Next task

Use a disposable Dylib record in the real admin to exercise destructive delete and version edit/delete. Then generate one Objective-C ZIP and verify `legacyDylibURLs` / `legacyApiFaceURLs` against the configured API Base URL. If those pass, prepare the 2410 release metadata and formal release gate.