# Known Issues and Refactor Backlog

## P0 — 2026092406 real BaoTa / device validation pending

- 2406 pre-release code checkpoint `38405039baf83de0e1bcb5ae2db4f4645c349bed` and all automated gates are green, but `source-v2026092406` has NOT been published.
- A non-release BaoTa/staging artifact is now available from Acceptance Package Run `36100165394`, Artifact ID `10849162793`; inner online-update ZIP SHA256 is `ae2e6b34ce5675b76afafe8f96711d81d866cf66770ffefc0c393fecf0ad3bf0`.
- Required before release: real BaoTa migration/online-update, real iOS device card-scope matrix, App identity spoof-negative testing, offline app_plus identity-cache negative testing, update/notice rendering, and server/domain migration failover testing.
- CI success is not treated as production runtime proof.

## P0 — Acceptance artifact is not a formal release

- `zonoe-2406-acceptance-36100165394` is explicitly marked `ACCEPTANCE ONLY - NOT A FORMAL RELEASE`.
- It does not create a GitHub tag or Release and does not change formal `VERSION`, `public/update/ver.txt`, or `ver.json`; stable metadata remains `2026092405` until production acceptance completes.
- Do not expose this artifact as the normal production update feed before all P0 BaoTa/device checks pass.

## P0 — scope=3 App identity requires parsed + actively bound IPA data

- App-specific `app_plus` is intentionally server-derived; the client does not get to declare `app_id`.
- The server requires matching parsed App identity material and an active `ipa_asset -> ipa_category_binding -> fa_category.id` relationship.
- If an App has not been parsed again after the 2406 identity schema is deployed, or has no active binding, scope=3 must not silently grant high privilege.
- Production rollout therefore requires re-parsing at least the actively sold/authorized Apps before validating scope=3.

## P0 — BundleID-only spoof still needs real-device confirmation

- 2406 no longer treats BundleID as sufficient App identity. Matching also uses executable and Mach-O `LC_UUID` against server-side parsed identity.
- `tests/dylib_runtime_access_mysql_test.php` validates the negative case on PHP 7.0 + MySQL 5.7 + real ThinkPHP `Db`: changing only BundleID does not resolve the spoofed App and does not grant `app_plus`.
- The same negative case still needs a real-device test because runtime collection/hooking behavior cannot be fully proven by server-side CI.

## P0 — app_plus offline cache identity still needs real-device confirmation

- Online `scope=3 -> app_plus` and its offline grace now use the same identity boundary: BundleID + `CFBundleExecutable` + main Mach-O UUID.
- The iOS client clears the cached authorization and refuses offline `app_plus` if the current executable or UUID does not match the cached resolved App identity.
- Contract coverage and real iPhoneOS arm64 compilation are green, but a real-device offline negative test is still required before release.

## P1 — Runtime endpoint migration still has a physical discovery boundary

- 2406 supports multiple Bootstrap endpoints, multiple API endpoints, signed runtime config, Last-Known-Good cache, legacy direct endpoint fallback and offline authorization grace.
- Admin now rejects the known unsafe state “Bootstrap configured but zero API endpoints”; Bootstrap/API both empty remains a valid legacy direct-endpoint configuration.
- This cannot provide impossible recovery: if all Bootstrap endpoints, all legacy endpoints and every usable local cached config are unavailable simultaneously, an old client cannot discover a future server from nothing.
- Production migration tests must deliberately break one Bootstrap, rotate the primary API domain, then validate failover and LKG behavior.

## P1 — BaoTa / old InnoDB runtime-identity index still needs real migration proof

- The runtime identity lookup fields remain full length, while `idx_runtime_identity` uses `bundle_id(64), executable(64), macho_uuid(36)` prefixes to reduce utf8mb4 composite-key pressure on conservative MySQL 5.7/InnoDB installations.
- GitHub MySQL 5.7 applies the 2406 migration twice successfully, but the user's actual BaoTa/MySQL settings can differ and must still be verified before release.

## P1 — Remote notices and update messages are server controlled

- Update title/body/button text and generic runtime notices are intentionally server-driven.
- Keep authorization and independent paid entitlements separate from notice/update rendering. A notice must never implicitly elevate `basic/app_plus/global_plus`.
- Button actions/URLs require real-device validation so malformed or stale server content cannot degrade the menu flow.

## P1 — Independent entitlements remain independent

- `basic`, `app_plus` and `global_plus` describe Dylib/menu capability only.
- Cloud-save or other separately purchased entitlement must continue to use its own validation and expiry rules.
- Do not infer cloud-save ownership from a full-source or App-specific menu card unless that business rule is explicitly changed later.

## P1 — Historical Dylib App bindings are retained intentionally

- 2405 `dylib_app_binding` records and admin endpoints remain for compatibility/audit/history.
- 2406 active verification no longer reads them as the App allow-list.
- Do not delete historical rows merely because the new runtime model no longer uses them for allow/block.

## P1 — Disabled Dylib intentionally resolves as unknown

- The verification service selects `dylib_key` with `enabled=1` and returns `dylib_unknown / block` when absent.
- This remains an intentional fail-closed protocol behavior; the admin UI describes it as “未注册或已停用”.

## P1 — Dylib deletion is history-protected

- The schema has no Dylib soft-delete column and no foreign keys.
- A registration with any version, historical BundleID binding or verification log is not hard-deleted; the admin must disable it so audit and policy history remain intact.
- A completely unused registration may be hard-deleted after the existing second-confirmation flow.

## P0 — 2026092405 / 2026092404 real BaoTa runtime verification remains pending

- `source-v2026092405` was formally released and its CI/E2E succeeded, but the user's real BaoTa Dylib lifecycle/UI verification remains outstanding.
- 2404 FPM scan/parse, live refresh and software-source response fixes also still require real BaoTa observation.

## P1 — PHP-FPM worker occupancy during in-process scan/parse

- `fastcgi_finish_request()` returns the browser response before queue consumption finishes, but that FPM worker remains occupied until its current scan/parse drain completes.
- Large OpenList trees and large parse queues still need production capacity observation.

## P1 — Cooperative cancellation / asset cleanup

- Active scan cancellation is cooperative and a small row batch may finish before the next cancellation check.
- IPA asset delete/clear must continue to preserve `fa_category` and actual OpenList files; real production data verification remains pending.

## P1 — Exact /license production Nginx interception

- `/authorization` remains the online-update-safe authorization query route.
- ThinkPHP still has `/license`, but production Nginx may intercept it before PHP.

## Stable announcement contract

- Public announcement authorization time remains single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons into the public announcement UI.
- Internal authorization scopes remain independent for permission enforcement.
