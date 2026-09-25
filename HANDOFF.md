# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Current stable release: `source-v2026092406`
- Previous stable release: `source-v2026092405`
- Release branch: `release/2026092406-dylib-global-app-support`
- Formal release commit: `798cbed23aedfefa2fd34a280ae70a01bb413e63`
- Draft PR: `#25`（not merged; release published directly from the release branch）
- Historical releases through 2026092405 remain immutable.

## 2026092406 implementation

1. Dylib validity and user feature entitlement are separate decisions. Active verification no longer uses per-Dylib BundleID binding as the runtime App allow-list.
2. Card scopes map to runtime access levels: `scope=2 -> basic`, `scope=3 + server-recognized current App -> app_plus`, `scope=1 -> global_plus`. Multiple valid cards use the highest access applicable to the current App.
3. scope=3 does not trust client `app_id` or BundleID alone. Server recognition uses BundleID + executable + Mach-O `LC_UUID`, then resolves through active `ipa_asset -> ipa_category_binding -> fa_category.id`.
4. `MachOInspector` reads `LC_UUID`; `IpaParserService` persists main executable identity in `fa_ipa_app_identity`. Only parsed assets with active IPA→category bindings participate in App-specific authorization.
5. Protocol v1 canonical HMAC order is unchanged; protocol v2 appends protocol/App identity/version fields.
6. Existing response fields stay compatible; new data includes `access_level`, `permissions`, `app_identity`, `app_update` and `notice`.
7. v2 session tokens and offline `app_plus` grace bind App identity. Offline recovery revalidates BundleID + executable + Mach-O UUID and clears cache on mismatch.
8. Parsed IPA version data powers App update detection. Update title/body/button labels/actions are server controlled.
9. Runtime notices support global/specific-App targeting, minimum access level, revision, priority, time window and button actions.
10. `/index/dylib_verify/config` provides runtime endpoint discovery. The iOS client supports multiple Bootstrap URLs, multiple API endpoints, signed config validation, Last-Known-Good, legacy direct endpoint fallback and offline authorization fallback.
11. Admin rejects Bootstrap-enabled configuration with zero API endpoints while retaining both-empty legacy direct-endpoint mode.
12. Runtime identity index uses conservative `64/64/36` utf8mb4 prefixes while storing full field values.
13. Admin workflow is Register → Integration → Permission Model → Runtime Config & Notices → Version Control → Verification Records.

## Formal online release evidence

- Formal release commit: `798cbed23aedfefa2fd34a280ae70a01bb413e63`.
- IPA Online Update Release Gate `36109410680`: SUCCESS.
  - formal version metadata validated as `2026092406`;
  - real online-update ZIP built and checked;
  - 2406 runtime payload present;
  - migration applied twice on MySQL 5.7;
  - PHP 7.0 runtime contracts and runtime-access MySQL integration passed.
- ZONOE Source Release `36109410642`: SUCCESS.
  - PHP 7.0 regression passed;
  - MySQL 5.7 migration gate passed;
  - release-gating `/appstore` concurrency matrix passed;
  - package-and-release passed;
  - real GitHub Release online-update E2E passed.
- GitHub Release: `source-v2026092406`, Release ID `396411187`, target commit `798cbed23aedfefa2fd34a280ae70a01bb413e63`, `draft=false`, `prerelease=false`.
- Release ZIP: asset ID `587832059`, 221101 bytes, GitHub digest `sha256:2d7affcc75ed7a33c1ec2d1c0ad39f1ed0e765ee30b2a4c6ccfc8cc7eb5339c5`.
- Release SHA file: asset ID `587832060`.
- CI release artifact: `zonoe-source-2026092406-online-update`, artifact ID `10852219528`, digest `sha256:7788e599a6b5cb33d69a204535bb698b181257c34f4d3491784a44af7092ca2b`.
- Real updater E2E resolved previous release `2026092405` and ended with:
  `OK phase13_github_online_update_e2e real_release=2026092406 base=2026092405 self_update=passed progress=passed history=passed db_migration=yes`.

## Compatibility boundary

- `source-v2026092405` is now previous stable and remains immutable.
- v1 signing remains accepted with original canonical order.
- Historical `dylib_app_binding` data/endpoints remain for compatibility/audit but are not used for active runtime App authorization.
- Bootstrap/API lists may both remain empty for legacy direct `endpointURL`; Bootstrap-nonempty/API-empty is rejected.
- Cloud save and other separately purchased entitlements remain independent from `basic/app_plus/global_plus`.

## Still requiring real BaoTa/device verification

- Actual user BaoTa update click `2026092405 -> 2026092406` and resulting updater history/backup.
- Actual BaoTa MySQL application of the 2406 migration and runtime-identity index.
- Re-parse at least one actively bound IPA to populate `fa_ipa_app_identity`.
- Real iOS behavior for scope=2 / scope=3 / scope=1 and multi-card access merging.
- BundleID-only spoof negative test on device.
- Offline `app_plus` identity-cache negative test on device.
- Game update notice / arbitrary server text / runtime notice button actions.
- API-domain migration, Bootstrap failover, Last-Known-Good and offline-grace behavior under real network failure.

## Next production verification sequence

1. On a real 2405 installation, use **GitHub 在线更新**; it should discover `2026092406` from the published Release.
2. Confirm the updater finishes at 100%, local `ver.json` / `public/update/ver.txt` become `2026092406`, and update history contains a successful `2405 -> 2406` entry with backup.
3. Confirm `fa_ipa_app_identity`, `fa_dylib_runtime_config`, `fa_dylib_runtime_notice` and the runtime identity index exist.
4. Re-parse an actively bound App and confirm `fa_ipa_app_identity` is populated.
5. Run real-device authorization matrix and spoof/offline negative cases.
6. Validate updates/notices and endpoint migration/failover/LKG/offline grace.
