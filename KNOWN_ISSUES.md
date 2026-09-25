# Known Issues and Refactor Backlog

## P0 — 2026092406 is formally online, but real BaoTa/device acceptance is still pending

- `source-v2026092406` is formally published from commit `798cbed23aedfefa2fd34a280ae70a01bb413e63`.
- Final IPA Online Update Release Gate `36109410680` — SUCCESS.
- ZONOE Source Release `36109410642` — SUCCESS.
- Real GitHub Release online-update E2E `2026092405 -> 2026092406` — SUCCESS with `self_update=passed progress=passed history=passed db_migration=yes`.
- Therefore the repository/GitHub online-update path is verified. The remaining P0 is the user's actual BaoTa installation and actual iOS devices; CI/E2E is not treated as proof of those environments.

## P0 — Real BaoTa online update still needs one observed run

- A real 2405 installation should now discover `2026092406` through the existing **GitHub 在线更新** button.
- After the real update, verify local `ver.json` and `public/update/ver.txt` are `2026092406`, update history is successful, backup exists, and the three 2406 tables were created.
- If the user's server does not discover the release despite the real GitHub E2E passing, investigate that server's outbound GitHub API/TLS/DNS/proxy connectivity rather than rebuilding the release blindly.

## P0 — scope=3 App identity requires parsed + actively bound IPA data

- App-specific `app_plus` is server-derived; the client does not declare authoritative `app_id`.
- The server requires matching parsed identity and active `ipa_asset -> ipa_category_binding -> fa_category.id`.
- After the 2406 schema is installed, re-parse at least the actively sold/authorized Apps so `fa_ipa_app_identity` is populated.
- Unparsed or unbound Apps intentionally cannot receive `app_plus`.

## P0 — BundleID-only spoof still needs real-device confirmation

- 2406 does not treat BundleID as sufficient App identity; matching also uses executable and Mach-O `LC_UUID` against server-side parsed identity.
- `tests/dylib_runtime_access_mysql_test.php` validates the negative case on PHP 7.0 + MySQL 5.7 + real ThinkPHP Db.
- The same negative case still needs a real-device test because runtime collection/hooking cannot be fully proven by server-side CI.

## P0 — app_plus offline cache identity still needs real-device confirmation

- Online scope=3/app_plus and offline grace use the same identity boundary: BundleID + `CFBundleExecutable` + main Mach-O UUID.
- The iOS client clears cached authorization and refuses offline `app_plus` if executable or UUID no longer matches the cached resolved App identity.
- Contract coverage and iPhoneOS arm64 compilation are green; real-device offline negative testing remains required.

## P1 — Runtime endpoint migration still has a physical discovery boundary

- 2406 supports multiple Bootstrap endpoints, multiple API endpoints, signed runtime config, Last-Known-Good, legacy direct endpoint fallback and offline authorization grace.
- Admin rejects “Bootstrap configured but zero API endpoints”; Bootstrap/API both empty remains valid legacy direct-endpoint mode.
- If every Bootstrap, legacy endpoint and usable local cached config is unavailable simultaneously, an old client cannot discover a future server from nothing.
- Production tests should rotate the primary API domain, break one Bootstrap, and verify failover/LKG/offline grace.

## P1 — BaoTa / old InnoDB runtime-identity index still needs real migration proof

- Runtime identity fields remain full length while `idx_runtime_identity` uses `bundle_id(64), executable(64), macho_uuid(36)` prefixes.
- GitHub MySQL 5.7 applies the migration twice successfully, including the formal release gate, but actual BaoTa/MySQL settings still require one observed migration run.

## P1 — Remote notices and update messages are server controlled

- Update title/body/button text and generic runtime notices are intentionally server driven.
- Notices must never elevate `basic/app_plus/global_plus` or imply ownership of independent paid entitlements.
- Button actions/URLs still require real-device rendering/behavior validation.

## P1 — Independent entitlements remain independent

- `basic`, `app_plus` and `global_plus` describe Dylib/menu capability only.
- Cloud save and other separately purchased entitlements continue to use independent validation/expiry rules.

## P1 — Historical Dylib App bindings are retained intentionally

- 2405 `dylib_app_binding` records and admin endpoints remain for compatibility/audit/history.
- 2406 active verification no longer reads them as the App allow-list.

## P1 — Disabled Dylib intentionally resolves as unknown

- Verification selects `dylib_key` with `enabled=1`; disabled/unregistered resolves as `dylib_unknown / block`.
- This remains intentional fail-closed behavior.

## P1 — Dylib deletion is history-protected

- A Dylib registration with versions, historical BundleID binding or verification logs is not hard-deleted; disable it to preserve audit/policy history.
- A completely unused registration may be hard-deleted through the existing confirmation flow.

## P1 — PHP-FPM worker occupancy during in-process scan/parse

- `fastcgi_finish_request()` can return the browser response before queue consumption finishes, but that FPM worker remains occupied until its drain completes.
- Large OpenList trees and large parse queues still need production capacity observation.

## P1 — Cooperative cancellation / asset cleanup

- Active scan cancellation is cooperative and a small batch may finish before the next cancellation check.
- IPA asset delete/clear must continue to preserve `fa_category` and actual OpenList files.

## Stable announcement contract

- Public announcement authorization time remains single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons into the public announcement UI.
- Internal authorization scopes remain independent for permission enforcement.
