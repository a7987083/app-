# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Stable release: `source-v2026092405`
- Stable branch: `release/2026092405-dylib-lifecycle-integration`
- Stable release commit: `f2cb8536b2a5196b4dab1c135c74033f740ed398`
- Active development branch: `release/2026092406-dylib-global-app-support`
- Runtime code checkpoint: `b5c3c00bf774583aed0879c6524d3ece1f1151e0`
- Verification-hardening checkpoint: `f713e449e3a2a6ec0a0aa4405984063093cb23b1`
- Draft PR: `#25`
- 2406 is NOT tagged/released yet.

## 2026092406 implementation

1. Dylib validity and user feature entitlement are separate decisions. The active verifier no longer uses per-Dylib BundleID binding as the App allow-list.
2. Card scopes map to runtime access levels:
   - `scope=2` -> `basic`
   - `scope=3` + server-recognized current App -> `app_plus`
   - `scope=1` -> `global_plus`
   When the UDID has multiple valid cards, the service computes the highest access level applicable to the current App.
3. `scope=3` does not trust client-provided `app_id` and does not rely on BundleID alone. The runtime supplies App identity material; the server recognizes the App using BundleID + executable + Mach-O `LC_UUID`, then resolves through active `ipa_asset -> ipa_category_binding -> fa_category.id`.
4. `MachOInspector` now reads `LC_UUID`; `IpaParserService` persists the main executable identity in `fa_ipa_app_identity`. Only `parsed` assets with an active IPA→category binding participate in App-specific authorization.
5. Protocol v1 canonical HMAC order is unchanged. Protocol v2 appends protocol/App identity/version fields after the complete v1 canonical payload.
6. Existing response fields stay compatible. New response data includes `access_level`, `permissions`, `app_identity`, `app_update` and `notice`.
7. v2 session tokens bind recognized App ID, access level and Mach-O UUID so an App-specific high-privilege session cannot simply be reused in another App.
8. Parsed IPA version data powers game-update detection. Update title/body/button labels/actions are server-controlled.
9. Runtime notices support global/specific-App targeting, minimum access level, revision, priority, time windows and button actions.
10. `/index/dylib_verify/config` provides runtime endpoint discovery. The iOS client supports multiple Bootstrap URLs, multiple API endpoints, signed configuration validation, Last-Known-Good cache, legacy direct endpoint fallback and offline authorization fallback.
11. Admin workflow is now Register → Integration → Permission Model → Runtime Config & Notices → Version Control → Verification Records. The old binding data/API remains for compatibility/history but is no longer presented as the main authorization workflow.
12. 2406 includes an idempotent MySQL 5.7 migration, clean-install schema updates, contract tests and online-update payload inclusion.

## Validation evidence

- IPA Online Update Release Gate `36088476047`: SUCCESS on runtime code checkpoint `b5c3c00...`.
  - 2406 runtime files present in the real online-update ZIP.
  - PHP 7.0 checks passed.
  - 2406 migration applied twice on MySQL 5.7 successfully.
  - 2406 runtime contract passed.
- Verification-hardening checkpoint `f713e449...` adds `tests/dylib_runtime_access_mysql_test.php` and runs it under PHP 7.0 + MySQL 5.7 against the real ThinkPHP `Db` layer and `DylibRuntimeAccessService`.
- IPA Data Center CI `36095035822`: SUCCESS.
  - Real runtime-access matrix: no card -> block; scope=2 -> basic; matching scope=3 -> app_plus; non-matching scope=3 -> block; BundleID-only spoof -> block; scope=1 -> global_plus; scope=2 + scope=3 multi-card merge -> app_plus on matching App / basic elsewhere; stale parsed identity -> rejected.
  - PHP 7.0, MySQL 5.7, 100k scale contracts and real iPhoneOS SDK arm64 compile all succeeded in the same CI run.
- Same checkpoint PR checks:
  - Regression Checks `36095035702`: SUCCESS.
  - Phase14 Production Hardening `36095035738`: SUCCESS.
  - Phase 17.2 Authorization Integrity `36095035839`: SUCCESS.

## Compatibility boundary

- Published `source-v2026092405` remains immutable and is still the stable release.
- Historical `dylib_app_binding` records/endpoints are retained; 2406 simply removes them from the active runtime allow/block decision.
- v1 signing remains accepted using the original canonical order.
- Cloud-save or other separately purchased entitlement must remain independent from `basic/app_plus/global_plus` unless explicitly designed otherwise.

## Not yet production-verified

- Real BaoTa application of the 2406 migration / online-update payload.
- Real iOS device behavior for `scope=2`, `scope=3`, `scope=1`, and multiple valid cards on one UDID.
- Negative device test: another game with only a modified BundleID must not receive `scope=3 app_plus`.
- Game-update notice rendering and arbitrary server-controlled title/body/button text.
- Runtime notice targeting and button actions.
- API-domain migration, Bootstrap failover, Last-Known-Good config and offline-grace behavior under real network failures.

## Next production verification sequence

1. Deploy the 2406 migration in a test/BaoTa environment; run it twice and confirm no duplicate/DDL failure.
2. Re-parse at least one IPA already actively bound to `fa_category` so `fa_ipa_app_identity` is populated.
3. With one real Dylib/UDID, test `scope=2`: expect `basic`, normal menu true, extra menu/features false.
4. Test `scope=3` in the bound App: expect `app_plus`; test the same card in another App: it must not obtain App-specific high privilege.
5. Modify only BundleID on a different App and repeat: server identity recognition must reject the App-specific match.
6. Test `scope=1`: expect `global_plus` without App-specific restriction.
7. Test multiple cards on one UDID and verify highest applicable access is selected for the current App.
8. Publish a game-update message and a generic runtime notice; verify server-supplied title/body/buttons render correctly.
9. Change the primary API endpoint in runtime config, break one Bootstrap endpoint, and verify failover/LKG behavior without recompiling the Dylib.
10. Test temporary total network failure within offline grace, then after expiry.
11. Only after these real checks pass should `source-v2026092406` release metadata/tag be prepared.
