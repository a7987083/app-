# Software Source Development Handoff

## Stable baseline

- Repository: `a7987083/app-`
- Stable branch: `release/2026091807-unified-announcement-expiry`
- Stable phase/version: `Phase 19.4.2 / 2026091807`
- Release commit: `1ef93306dec0a7b09bb99df55b785511d5b8b4c6`
- Release: `source-v2026091807`

## Active development

- Branch: `feature/phase20-ipa-management-v1`
- Phase: `20.7 code/CI complete`
- Verified code HEAD: `438db0823a2d03e220401509ce736da57bc99ab9`
- CI Run: `35410113307` — SUCCESS
- Production E2E: NOT VERIFIED

## Phase 20.7 completed capabilities

- Batch governance with `batch_hash`, per-item `plan_hash`, low-risk-only batch semantics.
- Failure queue using existing `ipa_operation_log`.
- Failed/interrupted recovery with re-preview, separate retry idempotency and superseded audit linkage.
- Range parser metrics from actual task item `result_json`.
- Preview-first bounded Retention cleanup.
- Batch ignore/unignore and `ignore_until` expiry sweep with audit records.
- FastAdmin permission-aware UI gating for high-risk Phase 20.7 controls.

## Safety invariants

- Batch governance never moves OpenList files automatically.
- Recovery never reuses an old governance plan.
- Retention requires preview + plan hash.
- Retention never selects `running`, `failed`, `interrupted`, `queued`, or `retrying` records.
- Retention is bounded to 1000 candidate rows per table per execution.
- Ignore lifecycle batches are bounded to 100 issues.

## Next task: Phase 20 RC closeout

1. Run controlled OpenList + MySQL E2E with backups available.
2. Verify batch governance and one explicit OpenList path mutation.
3. Exercise failed/interrupted retry and confirm audit linkage.
4. Exercise ignore expiry sweep.
5. Validate Range metrics against actual parser traffic.
6. Run Retention preview, inspect candidate IDs, then apply only after backup/restore validation.
7. Validate real admin permission groups and hidden controls.
8. Prepare RC packaging, migration SQL, online-update and rollback checklist.

## Existing production caveat

`/authorization` remains the online-update-safe authorization lookup URL. `/license` can still be intercepted by the production Nginx LICENSE rule before PHP.
