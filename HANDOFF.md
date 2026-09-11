# Software Source Development Handoff

## Repository / baseline
- Repository: `a7987083/app-`
- Development branch: `dev/software-source-v1`
- Stable branch: `main`
- Original stable baseline: `598235962ea328c6558fe4935fe19ba552c1490d`
- `main` remains untouched.

## Current architecture
- Framework: ThinkPHP 5.0.24 / FastAdmin-style.
- Public source: `/appstore` -> `application/index/controller/App.php::list()`.
- Self-service device transfer: `/unbind` -> `application/index/controller/Index.php::unbind()`.
- Public payload mapper: `application/common/library/AppStorePayload.php`.
- Legacy Category field semantic layer: `application/common/library/SourceAppRecord.php`.
- External source HTTP client: `application/common/library/SourceHttpClient.php`.
- Public source response encoder: `application/common/library/SourceResponse.php`.
- Shared config cache: `application/common/library/SourceConfigRepository.php`.
- Blacklist semantics: `application/common/library/BlacklistPolicy.php`.
- Trace semantics: `application/common/library/TraceMonitorPolicy.php`.
- Daily Category stats: `application/common/library/CategoryDailyStat.php`.
- Card generation: `application/common/library/CardCodeGenerator.php`.
- Card duration/stacking: `application/common/library/CardEntitlementPolicy.php`.
- Device transfer: `application/common/library/CardDeviceTransfer.php`.

## Compatibility boundary
Public source keys remain `name`, `message`, `identifier`, `sourceURL`, `sourceicon`, `payURL`, `unlockURL`, `apps`; app keys remain `name`, `type`, `version`, `versionDate`, `versionDescription`, `lock`, `downloadURL`, `isLanZouCloud`, `iconURL`, `tintColor`, `size`.

`APPSTORE: v2` still selects `appstore_v2`; all other header values use `appstore`. Plain output still strips runtime `UDID/Time`; encrypted output still retains them before encryption. Guest truthy-lock and licensed strict-`lock === "1"` behavior are preserved. The external endpoints remain `https://api.nuosike.com/api.php` and `https://api.nuosike.com/encrypt.php`.

## Phase 1-8 summary
- Extracted source payload mapping and compatibility tests.
- Fixed dylib null access, homepage child N+1, config caching, BaoTa DB placeholders and root Nginx rewrite packaging.
- Centralized blacklist/trace behavior and made relevant writes transactional.
- Added true Category server paging, full-database search, top+bottom pagination, lightweight default columns and add-without-parent-refresh.
- Fixed `cs/cstime` daily identity using `YYYYMMDD` with transaction + row lock.
- Replaced weak card generation with `random_bytes` while preserving visible card format.
- Added active-blacklist duplicate prevention and expired-history display.
- User reported Phase 8 deployment/admin behavior working correctly.

## Phase 9 — final closure
- User completed the production access-log audit for stale `App-mb.php` / `Index2.php` paths and then completed server cleanup.
- Both stale duplicate controllers were formally removed from `dev/software-source-v1`.
- Regression contracts now require both files to remain absent.
- Phase 9 closure CI Run `34545630620` passed PHP 7.0 / 8.2 / 8.4.
- A final Phase 9 BaoTa package is kept as the rollback/stable closure artifact.

## Phase 10A — HTTP / TLS / timeout / error handling
`SourceHttpClient` now owns the external encryption POST transport:
- TLS peer verification defaults ON.
- TLS hostname verification defaults to `2`.
- Connect timeout defaults to 5 seconds.
- Overall timeout defaults to 20 seconds.
- HTTP/cURL failures are logged with status/errno/error.
- Existing external endpoints and form body remain unchanged.
- Emergency compatibility rollback: `SOURCE_HTTP_VERIFY_TLS=0` restores the old peer-verification-off behavior without reverting code.
- Optional timeout overrides: `SOURCE_HTTP_CONNECT_TIMEOUT`, `SOURCE_HTTP_TIMEOUT`.

Important: the two real encryption endpoints still require a live smoke test with strict TLS before Phase 10 is promoted to production stable. The historical encrypted request was ~10.48s, so the default total timeout is intentionally above that observed latency.

## Phase 10B — semantic Category field layer
`SourceAppRecord` maps business names to legacy physical columns without changing the database:
- `download_url -> bt1a`
- `button_color -> bt1b`
- `file_size -> bt2a`
- `paid -> bt2b`
- plus version/description/icon/cloud/status/etc.

`AppStorePayload` and the public source query use this semantic layer. No `ALTER TABLE`, column rename or data migration is required.

## Phase 10C — source response layer
`SourceResponse` centralizes:
- plain-body runtime-field stripping and `@@@ -> \\n` behavior;
- `appstore` / `appstore_v2` wrapper serialization;
- final output path.

Header/status behavior was deliberately left as the legacy controller behavior to minimize client compatibility risk. Existing payload/equivalence tests plus new response tests lock the body contract.

## Stackable card authorization
Each card code is still one-time use (`jh=1` remains consumed), but unused new cards can be activated at any time while the same UDID has remaining authorization.

Activation base is:
`max(now, furthest active endtime for this UDID)`.

The new card duration is appended after that base, so repeated day/week/month/quarter/year cards can extend authorization without losing remaining time. Existing durations remain day=1d, week=7d, month=30d, quarter=90d, year=360d.

## Self-service device transfer
Public page: `/unbind`.

Customer submits:
- a card previously bound to the old device;
- old UDID;
- new UDID.

Rules:
- old/new UDID must use the existing supported 25/40-character format;
- the proof card must already belong to the old UDID;
- old device must currently have active authorization;
- active blacklist on either old/new device blocks self-service transfer;
- new UDID must not already have active authorization;
- on success, activated card history is moved to the new UDID so stacked entitlement and future proof-card use follow the replacement device;
- effective expiration does not change during transfer.

## CI
Phase 10 code CI Run `34546582035` passed PHP 7.0 / 8.2 / 8.4. It covers previous suites plus semantic source fields, unified response body, HTTP/TLS contract, stackable entitlement policy, transfer contract and Phase 10 controller architecture checks.

## Live validation still required for Phase 10
1. Plain `/appstore` regression.
2. Encrypted `appstore` with strict TLS.
3. Encrypted `appstore_v2` with strict TLS.
4. Activate a first test card, then activate at least two more before expiry and confirm endtime keeps extending from the prior furthest expiry.
5. Visit `/unbind`, transfer one active stacked test entitlement from old to new UDID and verify old loses access/new gains access with unchanged final expiration.
6. Verify blacklisted old/new devices and already-active target device are refused.

## Stability rules
1. Do not modify `main` until explicit promotion.
2. Keep the Phase 9 closure ZIP as rollback baseline while Phase 10 receives live validation.
3. Do not change the public encryption protocol/provider while validating 10A.
4. No physical Category DB field migration during 10B.
5. Each card remains one-time consumption even though entitlement time stacks.
6. BaoTa release ZIP must retain root `auto_install.json`, `import.sql`, `nginx.rewrite` and `BT_DB_*` placeholders.


## Phase 11 handoff

Phase 10 is the user-verified rollback baseline. Phase 11 adds authorization operations without changing appstore/appstore_v2 protocol keys. Core files: `AuthorizationSchema.php`, `AuthorizationPolicy.php`, `AuthorizationEventLog.php`, `AuthorizationLicense.php`, `CardDeviceTransfer.php`, admin `Authorization.php`, `/unbind`, `/unbind/query`, and `/license`. Default transfer policy is 3 total transfers, 1 successful transfer/day, 3600-second cooldown, 10 attempts/IP/hour; all are configurable in `fa_config`. `fa_kami.transfer_count` is inherited by newly stacked active cards so buying another card cannot reset transfer budget.
