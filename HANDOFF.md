# Software Source Development Handoff

## Repository / current baseline

- Repository: `a7987083/app-`
- Stable candidate branch: `feature/phase13-github-release`
- Stable candidate phase/version: `Phase 13.6 / 2026091203`
- Stable candidate commit: `d13ccb9ced56ca655a27cf13b5be0d6a33e724e2`
- Stable Release: `source-v2026091203`
- Stable CI: Run `34654867771` — SUCCESS
- Current development branch: `refactor/phase14-production-hardening`
- Current Phase: `14.1 Production Hardening`
- Current verified code commit: `3600ceb25190ca93deb85689a94d44aea57c709d`
- Phase14 CI: Run `34663424209` — SUCCESS on PHP 7.0 full regression.
- `main` is not the active development baseline and must not be changed implicitly.

## Current architecture

Framework: ThinkPHP 5.0.24 / FastAdmin-style.

### Public software-source path

`/appstore` -> `application/index/controller/App.php::list()`

Data flow:

`request/trace/header/UDID/code`
-> `SourceConfigRepository`
-> trace / blacklist checks
-> optional card activation
-> `fa_category` query through `SourceAppRecord` semantic columns
-> `AppStorePayload`
-> `SourceResponse`
-> plain response or `SourceHttpClient` external appstore/appstore_v2 encryption.

Public payload compatibility is protected by `appstore_payload_test`, `appstore_equivalence_test`, `appstore_semantic_equivalence_test` and `source_response_test`.

### Authorization path

- Card activation: currently orchestrated by `App::activateCode()` using `CardEntitlementPolicy`, `AuthorizationPolicy`, `AuthorizationSchema`, `AuthorizationEventLog` and `fa_kami`.
- `/unbind` + `/unbind/query`: `CardDeviceTransfer`.
- `/license`: `AuthorizationLicense`.
- `/dylib` and `/apiface`: `Index.php` legacy-compatible authorization checks.
- Admin Authorization Center: `application/admin/controller/Authorization.php`.

### Admin data path

- Category: server-side pagination/search/filter; parent Tree only built for add/edit.
- Kami: cryptographic card generation via `CardCodeGenerator`; remaining transfer quota editable and synchronized for active stacked rows.
- Black/Monitor: shared `BlacklistPolicy` and `TraceMonitorPolicy` semantics, but persistence/query orchestration is not fully centralized.

### Online update path

`admin/general/Config`
-> `UpdateManager`
-> `NuosikeUpdateSource` or `GitHubUpdateSource`
-> `UpdateHttpClient`
-> `UpdateInstaller`
-> `UpdateBackup` + `UpdateSqlRunner`
-> file/database/config/version update
-> `UpdateRuntimeStore` status/history
-> manual or automatic rollback.

GitHub stable updates require `zonoe-online-update.zip` plus matching SHA256. Strict HTTPS/TLS remains the default.

## Stable compatibility boundaries

Do not change during refactoring unless separately approved and tested:

1. `appstore / appstore_v2` public keys and wrapper behavior.
2. Guest truthy-lock and licensed strict `lock === "1"` semantics.
3. External encryption endpoints/protocol.
4. Card duration: day/week/month/quarter/year = 1/7/30/90/360 days.
5. Each card remains one-time consumed; authorization time may stack.
6. `transfer_count` means remaining device-transfer quota.
7. UDID accepted format remains the existing 25/40-character rule.
8. BaoTa package contract: `auto_install.json`, `import.sql`, `nginx.rewrite`, `BT_DB_*` placeholders.
9. Retired `App-mb.php` and `Index2.php` must remain absent.
10. No physical migration of legacy Category fields `bt1a/bt1b/bt2a/bt2b` without a dedicated migration plan.

## Phase 1-13 summary

- Phase 1-8: source payload extraction, duplicate/N+1 cleanup, config cache, blacklist/trace policy, Category server pagination, daily statistics fix, cryptographic card generation and deployment contract fixes.
- Phase 9: production audit completed and stale `App-mb.php` / `Index2.php` removed.
- Phase 10: source HTTP/TLS hardening, semantic Category fields, unified response layer, stacked authorization and `/unbind`.
- Phase 11: transfer quota/policies, audit logs, `/license`, Authorization Center and diagnostics.
- Phase 12/12.1: shared Nuosike/GitHub updater, SHA256, update lock, ZIP/path protections, DB/file backup and rollback; updater/diagnostic/quota hotfixes.
- Phase 13.1-13.6: stable GitHub Release pipeline, real staged progress, update history/rollback, updater self-update, local integrity manifest, real Release E2E and rollback runtime regression.

Detailed historical changes remain in `CHANGELOG_DEV.md`; release-specific summaries remain in `PHASE*_RELEASE.txt`.

## Phase 14.1 review findings and implemented changes

### P0 fixed on `refactor/phase14-production-hardening`

1. **Multi-package update was not transactionally atomic across packages.**
   Previously, package N could rollback itself while packages 1..N-1 remained applied. `UpdateManager` now gathers all package backups and restores the chain in reverse order on failure.

2. **Rollback could report success while file restore/delete silently failed.**
   `UpdateBackup` now checks restore directory creation, file copy and created-file deletion, still attempts DB restore, and throws if rollback is incomplete.

3. **Update ZIP/extracted cache lived below Web Root.**
   Temporary update cache moved from `public/update/cache` to `runtime/update/cache`.

4. **Update history order was nondeterministic for records created in the same second.**
   `UpdateRuntimeStore::recordHistory()` previously used only second-resolution filenames and `history()` relied on lexicographic file order. Fast update+rollback could therefore return the older row first. History filenames now include a monotonic microsecond sort key and the entry stores `created_at_us`.

### New regression coverage

`tests/phase14_update_atomicity_test.php` verifies:

- second-package failure rolls back both current and previous package backups in reverse order;
- overwritten files restore and update-created files are removed;
- rollback I/O failure cannot be silently marked successful;
- update cache is outside `public`;
- same-second history returns the most recently recorded item first with monotonic microsecond order.

Phase14 CI Run `34663424209` passed the existing Phase 1-13 regression suite plus the new/extended test under PHP 7.0.

## Open issues / deliberate non-changes

### P1 — transfer-history semantic mismatch

Current `CardDeviceTransfer::transfer()` moves only currently active rows (`endtime > now`) to the new UDID. Older handoff/build text says all activated-card history moves. Existing contract tests only lock the active-row implementation. Do **not** change either direction without an explicit stable-behavior decision and a real DB regression fixture.

### P1 — duplicated blacklist persistence/query behavior

`App.php` and `Index.php` each contain active blacklist lookup + first-hit `usetime` update; `CardDeviceTransfer` also performs its own blacklist queries. Candidate future extraction: `BlacklistRepository/BlacklistService`. Not part of Phase14.1 because current behavior is stable and covered only partially by integration tests.

### P1 — card activation still in controller

`App::activateCode()` owns DB transaction, card locking, stack calculation, transfer quota selection and event logging. Candidate future extraction: `CardActivationService`, but only after a DB-backed behavioral test is added.

### P1 — admin/update scale issues

- Authorization dashboard loads all blacklist rows and counts active rows in PHP.
- Authorization transfer/event lists use fixed 300-row caps, not server pagination.
- GitHubUpdateSource may request a SHA asset for each qualifying Release during a check.
- UpdateRuntimeStore history is still unbounded file storage; same-second order is fixed, but retention/indexing remains open.
- PHP DB backup uses repeated LIMIT/OFFSET and may become expensive on very large databases.

### P1 — database uniqueness

`fa_kami.kami` uniqueness is still enforced by generation/check logic rather than a DB unique index. Production duplicate audit + migration plan must precede any UNIQUE INDEX.

## Build / validation

Authoritative Phase14 CI workflow: `.github/workflows/phase14_refactor.yml`.

Current verified run:

- Run: `34663424209`
- Head code commit: `3600ceb25190ca93deb85689a94d44aea57c709d`
- PHP: 7.0
- Result: SUCCESS

This proves static/contract behavior and the new update atomicity/history-order fixtures. It does **not** replace a real BaoTa/MySQL/browser production smoke.

## Next Task — Phase 14.2

1. Real `2026091202 -> 2026091203` GitHub online upgrade.
2. Validate version, SHA256, files, DB, history and backup.
3. Manual rollback to `2026091202` from update history.
4. Validate program + DB + manifest + version restoration.
5. Re-upgrade to `2026091203`.
6. Failure-injection tests: SHA mismatch, corrupt ZIP, SQL failure, unwritable target, backup failure, lock conflict and low disk.
7. Only after the real loop passes should Phase14 hardening be considered promotable.

See `ROADMAP.md` for Phase 15-17 planning.
