# Software Source Development Handoff

## Current stable release

- Repository: `a7987083/app-`
- Branch: `release/2026092206-ipa-controls-regression-hotfix`
- Release: `source-v2026092206`
- Release commit: `2cf0e030eb2db3a4aa079462c7a4399720214d1b`
- Previous stable baseline: `source-v2026092205`
- IPA gate: `35782863429` — SUCCESS
- Formal release: `35782863008` — SUCCESS
- GitHub Release online-update E2E: `2026092205 -> 2026092206` — SUCCESS

## 2206 implementation

The regression hotfix changes four runtime files relative to 2205:

1. `application/admin/controller/IpaCenter.php`
2. `application/admin/controller/IpaSourceCenter.php`
3. `application/common/library/Ipa/IpaSoftwareSourceService.php`
4. `public/assets/js/backend/ipa_source_center.js`

Core behavior:

- Software-source test remains read-only and explicitly blocks table-row selection propagation.
- Software-source writes use guarded defaults and transactional rollback on exceptions/Throwable.
- Pause does not kill an active Parse Worker task; it prevents new work and only reclaims orphaned `parsing` rows if the worker is offline.
- Clear parse results first reclaims orphaned work, then refuses unsafe clearing while genuine parsing is active.
- Clearing preserves OpenList discovery records, source configuration and `fa_category`.
- No 2206 SQL migration was added.

## Release / update contract

- Existing `UpdateManager / UpdateInstaller` semantics are unchanged.
- Release asset: `zonoe-online-update.zip`, SHA256 `45553d5fd4ef5ef42f097486262ab27d223e166c8df83ffeec5144b03fa45e6f`.
- CI Artifact: `zonoe-source-2026092206-online-update` (artifact id `10719265057`).
- `UpdateIntegrity` sentinel files were unchanged from 2205, therefore `ver.json.file_sign` remains `f3f6e072f814d06403ce5e393967c9e2`.

## Verification boundary

Verified: source diff, GitHub CI, PHP 7.0 regression, MySQL 5.7 migration regression, HTTP load gate, package build, GitHub Release publication, GitHub Release online-update E2E.

Not yet verified: production BaoTa deployment and manual/real-device UI behavior.

## Next task

Deploy/update a production test instance from 2205 to 2206 and manually verify source save/test, pause/resume orphan recovery and clear-parse-results behavior. Do not rewrite the released 2206 history; subsequent fixes must use a new version/commit.
