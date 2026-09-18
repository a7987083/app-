# Development Changelog

## 2026-09-18 — Phase 19.4 Formal Online Update Release 2026091805

- Formal Release: `source-v2026091805`
- Release branch: `release/2026091805-phase19-4-dynamic-announcement`
- Release commit: `7126b378e031c6c7f5965d0f78b3e753b28c27df`
- Release Run: `35300195897` — SUCCESS
- Assets: `zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
- Real GitHub Release online-update E2E: `2026091804 -> 2026091805` — SUCCESS
- E2E output: `self_update=passed progress=passed history=passed db_migration=yes`.


## 2026-09-18 — Phase 19.4 Dynamic Announcement + License Routing

Baseline: `source-v2026091804@34fc713346eafe92f23d1f5fcf470526daf8ddb6`
Feature branch: `feature/2026091805-phase19-4-dynamic-announcement-license`
Feature code commit: `391de96f6a8f4da3d1f75b505acc876b07101608`
Phase 19.4 CI: Run `35296525450` — SUCCESS

- Added request-aware dynamic software-source announcement templates with default-value syntax.
- Added source statistics and scope-separated authorization variables without changing the public AppStore schema.
- Kept shared Legacy caches static by caching a sentinel and injecting request-specific announcement text after cache lookup and before encryption/transport.
- Bumped plain-body/encrypted-JSON cache namespaces to v2 to avoid mixing old cached bodies with the new sentinel format.
- Added admin variable insertion and optional-UDID live preview.
- Added exact Nginx routing contract: uppercase `/LICENSE` remains blocked while lowercase `/license` reaches ThinkPHP.
- Added missing authorization views/library plus Nginx rewrite to the online-update manifest.
- Added PHP 7.0 Phase19.4 contracts and ZIP-content assertions.
- Phase19.4 CI output: dynamic announcement/cache isolation/default syntax/admin/manifest passed; SourceResponse, Legacy cache, API Center, deployment, card policy and App scope regressions passed.
- Online-update test package built successfully with 56 program files and 9 SQL files.
- Stable 1804 release audit found Run `35291515730` failed only at final GitHub online-update E2E because the test requires the literal phrase `更新内容`; build, PHP 7.0, MySQL 5.7, load gate and package publishing all passed. Phase19.4 release notes now preserve that literal marker.

Not yet claimed: production Nginx reload, public `/license` GET/POST verification, iOS real-device announcement/encryption regression, or formal `2026091805` release.

## 2026-09-18 — Phase 19.1 V3 Client-safe Sync Contract / Release 2026091801

Baseline: `source-v2026091714@6a0ab0aee756ae89dc2cd89d9f3aac2ebdee2620`
Feature branch: `feature/phase19-1-client-sync-contract-v1`
Feature commit: `894a9b5b3380ac367a479536c380de37fe10f2bf`
Release branch: `release/2026091801-phase19-client-sync`
Release commit: `6e3c7a3af25843c3f329207bd6b4c4e06e182e57`
Release: `source-v2026091801`

- Added full-sync `snapshot_revision` pinning and before/after revision validation to prevent mixed-revision client snapshots.
- Added `snapshot_valid/restart_required` restart contract without changing legacy `/appstore`.
- Added `min_delta_since/min_since` retention-window metadata.
- Added delta `reset_required/reset_reason` for `history_gap` and `future_revision` recovery.
- Kept authorization/blacklist errors as `supported=1`; only protocol disable uses `supported=0` fallback.
- Added PHP 7.0 Phase19 contract workflow and MySQL 5.7 retention-window regression.
- Verified online-update package contains all Phase19 runtime files.
- Feature CI Run `35261910066` passed.
- Initial release Run `35262053356` found a Release Notes compatibility regression: E2E required literal Chinese `更新内容` marker. Runtime/update code was not changed.
- Fixed only Release Notes metadata in commit `6e3c7a3af25843c3f329207bd6b4c4e06e182e57`.
- Final Release CI Run `35262273073` passed PHP 7.0 full regression, MySQL 5.7 migrations, package/release and real GitHub online-update E2E.
- E2E result: `real_release=2026091801 base=2026091714 self_update=passed progress=passed history=passed db_migration=yes`.
- GitHub Release publishes `zonoe-online-update.zip` plus matching `.sha256`.

Compatibility: old `/appstore`, V3 toggle, plain/normal/V2 encryption, card authorization and BaoTa deployment contracts are unchanged.

## 2026-09-17 — Phase 15-18 progression summary

- Completed production updater hardening/operations follow-ups, data-integrity/card-scope work, authorization integrity and renewal-entry fixes.
- Added source performance/cache improvements and schema-adaptive compatibility fixes.
- Phase 18.1 introduced additive V3 `meta/apps/delta` endpoints with monotonic change revisions and keyset pagination while preserving `/appstore`.
- Phase 18.2 made source encryption an explicit mutually-exclusive disabled/normal/V2 server mode.
- Phase 18.3 added the `source_v3` switch and explicit `supported=0 + fallback=appstore` capability fallback, while business authorization failures remain V3-supported.
- Stable pre-Phase19 release was `source-v2026091714`.

## 2026-09-12 — Phase 14.1 Production Hardening

Baseline: `feature/phase13-github-release@d13ccb9ced56ca655a27cf13b5be0d6a33e724e2`
Development branch: `refactor/phase14-production-hardening`

- Fixed multi-package updater atomicity: if a later package fails, `UpdateManager` now restores the current package backup and every previously successful package backup in reverse order.
- Refactored manual update-history rollback and failed-install rollback to share Manager-level backup restoration helpers.
- Hardened `UpdateBackup::rollback()` so failed directory creation, file restore or update-created-file deletion cannot be silently reported as success; database restore is still attempted and incomplete rollback is surfaced explicitly.
- Moved update ZIP/extraction work cache from `public/update/cache` to `runtime/update/cache` so temporary packages and extracted PHP are not under Web Root.
- `UpdateInstaller` now exposes the current backup directory to the Manager for chain-level failure recovery.
- Added `tests/phase14_update_atomicity_test.php` for second-package failure, reverse-order chain rollback, normal file restore/removal, rollback I/O failure and private cache path.
- Added `.github/workflows/phase14_refactor.yml` running PHP 7.0 lint plus the full existing regression suite and the new Phase14 test.
- Initial Phase14 code CI Run `34663080449` passed.
- During documentation-sync regression, `phase13_update_runtime_test.php` exposed a real same-second history-order race: history files used only second-resolution names, so a rollback record created in the same second as its update could sort behind the older entry.
- Fixed `UpdateRuntimeStore::recordHistory()` with a monotonic microsecond sort key (`created_at_us`) while retaining a readable second prefix and compatibility with old history files.
- Extended `phase14_update_atomicity_test.php` to prove same-second history is newest-first and monotonic.
- Added `UpdateRuntimeStore.php` to Phase14 lint coverage.
- Final verified code commit: `3600ceb25190ca93deb85689a94d44aea57c709d`.
- Final Phase14 PHP 7.0 full-regression CI Run `34663424209` passed.
- Added `ROADMAP.md` and synchronized Phase14 state/handoff/build/known-issue documentation.

Compatibility: no public `appstore/appstore_v2` protocol changes, no authorization duration/quota changes, no Category schema migration, no Nuosike removal.

## 2026-09-12 — Phase 13.1-13.6 GitHub Release / updater closure

- Added stable GitHub Release publishing for `zonoe-online-update.zip` and mandatory `.sha256`.
- Added real staged online-update progress and Chinese update panel/result details.
- Added update history and manual rollback from successful update records.
- Added updater self-update coverage so UpdateManager/Installer/RuntimeStore and admin update entry can update through the same release package.
- Added local `ver.json.file_sign` synchronization and release-time integrity verification.
- Added real GitHub Release E2E plus rollback runtime regression.
- Stable candidate: version `2026091203`, commit `d13ccb9ced56ca655a27cf13b5be0d6a33e724e2`, Release `source-v2026091203`, CI Run `34654867771` SUCCESS.

## 2026-09-12 — Phase 11 Authorization Operations

- Phase 10 was user-verified and frozen as the production rollback baseline.
- Added configurable self-service transfer budget: total count, daily limit, cooldown and per-IP hourly attempt limit.
- Added `transfer_count` to card records; newly stacked cards inherit the active entitlement chain transfer count.
- Added `/unbind/query` UDID-only remaining-transfer lookup and updated `/unbind` UI with a remaining-count query section.
- Added `fa_card_transfer_log` audit history for successful and failed device transfers.
- Added `fa_authorization_event` history for activation, stacking, transfer and blacklist events.
- Added `/license` public authorization lookup requiring card + UDID.
- Added backend Authorization Center with overview, transfer logs, event logs and system diagnostics.
- Diagnostics cover DB, PHP/extensions, HTTPS/TLS, writable runtime/log/uploads, disk space, backup freshness and optional external encryption endpoint probes.
- Added idempotent runtime schema bootstrap plus `tools/phase11_upgrade.sql` for explicit in-place upgrades.
- Added Phase 11 regression/contract tests and BaoTa deployment artifact workflow.

## 2026-09-11 — Refactor Phase 10

### 10A — source HTTP transport hardening
- Added `SourceHttpClient` for the existing external encryption POST requests.
- TLS peer/hostname verification now defaults on; connect/total timeouts default to 5s/20s.
- cURL/HTTP failures are logged while the existing response envelope is preserved.
- Existing external encryption endpoints are unchanged.

### 10B — semantic source-app fields
- Added `SourceAppRecord` to map semantic names such as `download_url`, `button_color`, `file_size`, and `paid` onto legacy `bt1a/bt1b/bt2a/bt2b` columns.
- `AppStorePayload` and the public source query now use the semantic layer.
- No database column rename or schema migration was introduced.

### 10C — unified source response body
- Added `SourceResponse` for plain output, encrypted wrapper output and final send path.
- Existing plain runtime-field stripping, `appstore`, `appstore_v2` and transport-failure body semantics are covered by tests.

### Stackable authorization / device replacement
- Added `CardEntitlementPolicy`; unused cards remain one-time activation and duration stacks after the furthest active expiration.
- Added `/unbind` self-service device replacement while preserving active authorization expiration.

## 2026-09-11 — Refactor Phase 9

- Production audit completed and stale `App-mb.php` / `Index2.php` were removed.
- CI contracts require the retired controllers to remain absent.

## 2026-09-11 — Refactor Phase 8

- Category server pagination/search improvements and daily-stat root fix.
- Cryptographic card generation and blacklist maintenance hardening.
- Added legacy-controller cleanup gate and regression coverage.

## 2026-09-11 — Refactor Phase 7

- Replaced Category full-list rendering with true server-side pagination and database search.

## 2026-09-11 — Refactor Phase 6

- Centralized trace/monitor UDID parsing and production audit tooling.

## 2026-09-11 — Refactor Phase 5

- Fixed blacklist persistence false-success and added shared blacklist policy.

## 2026-09-11 — Refactor Phase 4

- Fixed BaoTa database placeholder/deployment packaging contract.

## 2026-09-11 — Refactor Phase 3

- Added shared `SourceConfigRepository` cache/invalidation.

## 2026-09-11 — Refactor Phase 2

- Centralized Category type normalization and transaction fixes.

## 2026-09-11 — Refactor Phase 1

- Extracted `AppStorePayload`, added equivalence tests, fixed dylib null access and homepage N+1 queries.

## 2026-09-12 — Refactor Phase 12 / 12.1

- Added shared Nuosike/GitHub hardened updater with SHA256, locking, ZIP/path protection, backup, rollback and integrity checks.
- Fixed local integrity verification, BaoTa diagnostics and transfer quota migration/semantics.

