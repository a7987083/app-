# Software Source Development Handoff

## Stable baseline

- Repository: `a7987083/app-`
- Stable branch: `release/2026091807-unified-announcement-expiry`
- Stable phase/version: `Phase 19.4.2 / 2026091807`
- Release commit: `1ef93306dec0a7b09bb99df55b785511d5b8b4c6`
- Release: `source-v2026091807`

## Active development

- Branch: `feature/phase20-ipa-management-v1`
- Phase: `20.7.3`
- Last verified development HEAD: `a573d6f2945edcd0ca7c44a2002f1212b4a19bb2`
- CI Run: `35409913235` — SUCCESS

### Completed production enhancements

- Phase 20.7.1: batch governance, batch plan hash, failure queue.
- Phase 20.7.2: failed/interrupted recovery, stale-running scan, safe re-preview retry, superseded audit linkage.
- Phase 20.7.3: Range metrics and preview-first retention cleanup.

### Safety invariants

- Batch governance never moves OpenList files automatically.
- Recovery never reuses an old governance plan.
- Retention requires preview + plan hash and only removes old completed history.
- Retention must not delete `running`, `failed`, `interrupted`, `queued`, or `retrying` records.
- Retention is bounded to 1000 candidate rows per table per execution.

## Next phase

Phase 20.7.4:

- batch ignore / ignore_until lifecycle and expiry recovery view;
- finer high-risk permission separation and UI capability gating;
- production audit summaries;
- real OpenList + MySQL production E2E;
- Phase 20 release-candidate closeout.

## Existing production caveat

`/authorization` remains the online-update-safe authorization lookup URL. `/license` remains routed in ThinkPHP but can still be intercepted by the production Nginx LICENSE rule before PHP.
