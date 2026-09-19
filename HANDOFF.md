# Software Source Development Handoff

## Stable baseline

- Repository: `a7987083/app-`
- Stable branch: `release/2026091807-unified-announcement-expiry`
- Stable phase/version: `Phase 19.4.2 / 2026091807`
- Release commit: `1ef93306dec0a7b09bb99df55b785511d5b8b4c6`
- Release: `source-v2026091807`

## Active development

- Branch: `feature/phase20-ipa-management-v1`
- Phase: `20.7 code/CI complete; RC repository + integration gates complete`
- Verified code HEAD: `3dc03a314c9f3f3ae8811025d3d9274f71558421`
- Phase 20 CI Run: `35412127806` / Run #70 — SUCCESS
- RC artifact: `phase20-rc-35412127806`
- Production / real pre-production E2E: NOT VERIFIED

## Phase 20.7 completed capabilities

- Batch governance with `batch_hash`, per-item `plan_hash`, low-risk-only batch semantics.
- Failure queue using existing `ipa_operation_log`.
- Failed/interrupted recovery with re-preview, separate retry idempotency and superseded audit linkage.
- Range parser metrics from actual task item `result_json`.
- Preview-first bounded Retention cleanup.
- Batch ignore/unignore and `ignore_until` expiry sweep with audit records.
- FastAdmin permission-aware UI gating for high-risk Phase 20.7 controls.

## RC repository / CI gates verified

- Full Phase 20 contract suite: PASS.
- PHP 7.0 online-update package build: PASS.
- Package SHA256 verification: PASS.
- Package contents include the required Phase 20 program payload and ordered `2026091901` ~ `2026091909` migration payload: PASS.
- Real MySQL 5.7 service migration gate: PASS.
- All nine Phase 20 migrations execute twice successfully on MySQL 5.7: PASS.
- Key Phase 20 tables and high-risk permission rules are present after migration, with no duplicate auth-rule names: PASS.
- OpenList client real HTTP loopback integration for health/list/get/rename/move and Authorization propagation: PASS.
- Recursive IPA discovery over HTTP and non-IPA filtering: PASS.
- Client-side unsafe rename target rejection: PASS.
- Updater automatic rollback after a forced post-copy failure: PASS.
- Rollback restores overwritten files, removes update-created files, preserves old version metadata, retains backup/database dump, and reports rollback success: PASS.
- Legacy update rollback regression: PASS.
- RC ZIP + SHA256 are retained as the GitHub Actions artifact `phase20-rc-35412127806` for 14 days.

## Safety invariants

- Batch governance never moves OpenList files automatically.
- Recovery never reuses an old governance plan.
- Retention requires preview + plan hash.
- Retention never selects `running`, `failed`, `interrupted`, `queued`, or `retrying` records.
- Retention is bounded to 1000 candidate rows per table per execution.
- Ignore lifecycle batches are bounded to 100 issues.

## Remaining external-environment acceptance

These checks require a real controlled deployment, real OpenList contents, real application data, or real FastAdmin admin groups. CI/mock results must not be presented as production verification.

1. Run full scan and representative IPA Range parsing against the intended OpenList endpoint.
2. Bind representative real IPA metadata to real categories and inspect governance anomalies.
3. Run controlled low-risk batch governance on persisted application data and inspect audit linkage.
4. Execute one explicitly approved real OpenList path mutation and verify metadata/binding path synchronization.
5. Force one failed/interrupted governance operation on persisted data, retry it, and verify superseded linkage.
6. Exercise real ignore/unignore/expiry lifecycle.
7. Validate Range metrics against observed HTTP Range traffic.
8. Run Retention preview on a backed-up dataset; restore backup; only then run bounded Retention apply.
9. Validate real FastAdmin permission groups so hidden controls and API denial agree.
10. Perform one controlled install/restore drill on the target deployment and confirm application usability after rollback.

## RC promotion rule

Do not change formal release metadata or create a Phase 20 tag/release until the external-environment acceptance above is recorded as passed. After that, select the RC version, update version metadata and release notes, run the formal release workflow, and create the RC only from a green release run.

## Existing production caveat

`/authorization` remains the online-update-safe authorization lookup URL. `/license` can still be intercepted by the production Nginx LICENSE rule before PHP.
