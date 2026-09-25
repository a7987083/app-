# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Stable release: `source-v2026092405`
- Stable branch: `release/2026092405-dylib-lifecycle-integration`
- Stable release commit: `f2cb8536b2a5196b4dab1c135c74033f740ed398`
- Active development branch: `release/2026092406-dylib-global-app-support`
- Runtime code checkpoint: `b5c3c00bf774583aed0879c6524d3ece1f1151e0`
- Verification-hardening checkpoint: `f713e449e3a2a6ec0a0aa4405984063093cb23b1`
- Final pre-release code checkpoint: `38405039baf83de0e1bcb5ae2db4f4645c349bed`
- Acceptance-package workflow checkpoint: `39bfab3629b3997d1fdbe13c754ace088bbac689`
- Draft PR: `#25`
- 2406 is NOT tagged/released yet.

## 2026092406 implementation

1. Dylib validity and user feature entitlement are separate decisions. The active verifier no longer uses per-Dylib BundleID binding as the App allow-list.
2. Card scopes map to runtime access levels: `scope=2 -> basic`, `scope=3 + server-recognized current App -> app_plus`, `scope=1 -> global_plus`. Multiple valid cards are merged by highest access applicable to the current App.
3. `scope=3` does not trust client-provided `app_id` and does not rely on BundleID alone. The server recognizes the App using BundleID + executable + Mach-O `LC_UUID`, then resolves through active `ipa_asset -> ipa_category_binding -> fa_category.id`.
4. `MachOInspector` reads `LC_UUID`; `IpaParserService` persists the main executable identity in `fa_ipa_app_identity`. Only `parsed` assets with an active IPA→category binding participate in App-specific authorization.
5. Protocol v1 canonical HMAC order is unchanged. Protocol v2 appends protocol/App identity/version fields after the complete v1 canonical payload.
6. Existing response fields stay compatible. New response data includes `access_level`, `permissions`, `app_identity`, `app_update` and `notice`.
7. v2 session tokens bind recognized App ID, access level and Mach-O UUID so an App-specific high-privilege session cannot simply be reused in another App.
8. `app_plus` offline grace now revalidates cached BundleID + executable + Mach-O UUID against the running App before restoring high privilege. Identity mismatch clears the offline cache. `basic/global_plus` retain their intended all-App semantics.
9. Parsed IPA version data powers game-update detection. Update title/body/button labels/actions are server-controlled.
10. Runtime notices support global/specific-App targeting, minimum access level, revision, priority, time windows and button actions.
11. `/index/dylib_verify/config` provides runtime endpoint discovery. The iOS client supports multiple Bootstrap URLs, multiple API endpoints, signed configuration validation, Last-Known-Good cache, legacy direct endpoint fallback and offline authorization fallback.
12. Admin runtime config rejects Bootstrap-enabled configurations with zero API endpoints, while keeping the both-empty legacy direct-endpoint mode valid.
13. `fa_ipa_app_identity.idx_runtime_identity` uses conservative `64/64/36` utf8mb4 index prefixes for better old MySQL 5.7/InnoDB compatibility; stored fields remain full length.
14. Admin workflow is Register → Integration → Permission Model → Runtime Config & Notices → Version Control → Verification Records. Old binding data/API remains for compatibility/history but is no longer the main authorization workflow.
15. 2406 includes an idempotent MySQL 5.7 migration, clean-install schema updates, contract tests and online-update payload inclusion.

## Validation evidence

- Final pre-release code checkpoint: `38405039baf83de0e1bcb5ae2db4f4645c349bed`.
- IPA Online Update Release Gate `36097524318`: SUCCESS.
  - real 2406 runtime files and migration are present in the online-update ZIP;
  - PHP 7.0 passed;
  - migration applied twice on MySQL 5.7;
  - phase2405/phase2406/signing/Mach-O contracts and real runtime-access MySQL integration passed.
- IPA Data Center CI `36097528583`: SUCCESS.
  - PHP 7.0 + MySQL 5.7 + real ThinkPHP `Db` authorization matrix passed;
  - 100k scale integration passed;
  - real iPhoneOS SDK arm64 compile of the updated `ZONVerifyClient.m` passed.
- Runtime-access matrix covers no-card block; scope=2 basic; scope=3 match app_plus; scope=3 mismatch block; BundleID-only spoof block; scope=1 global_plus; multi-card highest-applicable access; stale identity rejection.
- Regression Checks `36097528593`: SUCCESS.
- Phase14 Production Hardening `36097528557`: SUCCESS.
- Phase 17.2 Authorization Integrity `36097528546`: SUCCESS.
- Non-release acceptance workflow `Dylib 2406 Acceptance Package` Run `36100165394`: SUCCESS.
  - Artifact name: `zonoe-2406-acceptance-36100165394`.
  - Artifact ID: `10849162793`.
  - Artifact digest: `sha256:c4245424062f5a893bd6791dee119b8e72a7341a43f608ecec5d94a743cee20f`.
  - Inner `zonoe-online-update.zip` SHA256: `ae2e6b34ce5675b76afafe8f96711d81d866cf66770ffefc0c393fecf0ad3bf0`.
  - The artifact is acceptance-only: it does not create a release/tag and leaves formal version metadata at stable `2026092405`.

## Compatibility boundary

- Published `source-v2026092405` remains immutable and is still the stable release.
- Historical `dylib_app_binding` records/endpoints are retained; 2406 simply removes them from the active runtime allow/block decision.
- v1 signing remains accepted using the original canonical order.
- Both Bootstrap/API lists may remain empty for legacy clients that use a direct `endpointURL`; only the unsafe Bootstrap-nonempty/API-empty configuration is rejected.
- Cloud-save or other separately purchased entitlement must remain independent from `basic/app_plus/global_plus` unless explicitly designed otherwise.

## Not yet production-verified

- Real BaoTa application of the 2406 migration / online-update payload.
- Real iOS device behavior for `scope=2`, `scope=3`, `scope=1`, and multiple valid cards on one UDID.
- Negative device test: another game with only a modified BundleID must not receive `scope=3 app_plus`.
- Offline device test: cached `app_plus` must be rejected when executable/Mach-O UUID no longer matches the cached resolved App identity.
- Game-update notice rendering and arbitrary server-controlled title/body/button text.
- Runtime notice targeting and button actions.
- API-domain migration, Bootstrap failover, Last-Known-Good config and offline-grace behavior under real network failures.

## Next production verification sequence

1. Download/use acceptance Artifact `10849162793` in a test/BaoTa environment; verify SHA256 before installation.
2. Deploy the 2406 migration; run it twice and confirm no duplicate/DDL failure, including the runtime identity index on the actual InnoDB configuration.
3. Re-parse at least one IPA already actively bound to `fa_category` so `fa_ipa_app_identity` is populated.
4. With one real Dylib/UDID, test `scope=2`: expect `basic`, normal menu true, extra menu/features false.
5. Test `scope=3` in the bound App: expect `app_plus`; test the same card in another App: it must not obtain App-specific high privilege.
6. Modify only BundleID on a different App and repeat: server identity recognition must reject the App-specific match.
7. After obtaining `app_plus` online, break verification and change the running App identity; cached offline high privilege must not follow the spoofed/different executable or Mach-O UUID.
8. Test `scope=1`: expect `global_plus` without App-specific restriction.
9. Test multiple cards on one UDID and verify highest applicable access is selected for the current App.
10. Publish a game-update message and a generic runtime notice; verify server-supplied title/body/buttons render correctly.
11. Confirm admin rejects Bootstrap-nonempty/API-empty config; then configure valid redundant Bootstrap/API endpoints and test migration/failover/LKG without recompiling the Dylib.
12. Test temporary total network failure within offline grace, then after expiry.
13. Only after these real checks pass should `source-v2026092406` release metadata/tag be prepared.
