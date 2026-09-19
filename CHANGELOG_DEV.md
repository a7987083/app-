# Development Changelog

## 2026-09-19 — Phase 20.7 production enhancement line

- Restored Phase 20.6 CI by loading `think\Exception` in the isolated writeback contract test.
- Added Phase 20.7.1 batch governance with batch plan hashing, per-item plan validation, safe-mode restrictions, and a governance failure queue.
- Added Phase 20.7.2 failed/interrupted recovery. Retry always re-previews current state, uses a separate recovery idempotency key, and marks recovered source operations `superseded`.
- Added Phase 20.7.3 Range metrics from parser task `result_json`: parsed/reused counts, transferred bytes, request count, averages, and bounded 7/30/90-day windows.
- Added preview-first Retention cleanup with plan hashing, protected failure/in-flight states, and bounded batches of at most 1000 rows per table.
- Added Phase 20.7.4 ignore lifecycle: batch ignore/unignore, `ignore_until` expiry sweep, ignored/expired queue, and audit operations.
- Added FastAdmin `$auth->check()` UI gating for high-risk batch governance, retry, retention, and lifecycle controls.
- Added production permissions for Range metrics, Retention, recovery, and lifecycle endpoints.
- Final Phase 20.7 code/UI CI Run `35410113307` — SUCCESS at `438db0823a2d03e220401509ce736da57bc99ab9`.
- Real production OpenList + MySQL E2E remains not verified; next step is RC closeout validation.

## 2026-09-18 — Phase 19.4.2 / Release 2026091807

- Collapsed announcement expiry/remaining-time into one public authorization clock.
- Removed scope-specific expiry/remaining buttons from the announcement editor.
- Kept entitlement scopes internally separate with priority: full source > partial Apps > verify-only.
- Unified no-entitlement/expired text to `已过期或未解锁本源`.
- Simplified `[授权摘要]` to status + expiry + remaining (+ App count for partial authorization).
- Added idempotent migration `2026091807_unified_announcement_expiry.sql`.
- Retained runtime parsing of old scope-specific tokens but mapped every one to the generic clock.
- Feature CI Run `35306216020` — SUCCESS.
- Release Run `35306308086` — SUCCESS.
- Release `source-v2026091807` published.
- Online-update E2E `2026091806 -> 2026091807`: `self_update=passed progress=passed history=passed db_migration=yes`.
