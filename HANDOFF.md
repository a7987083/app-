# Software Source Development Handoff

## Stable baseline

- Repository: `a7987083/app-`
- Stable branch: `release/2026091901-phase20-ipa-management`
- Stable phase/version: `Phase 20 / 2026091901`
- Release commit: `65d464b4d86f7bca9eb0ad89e96edcbf0ab5a9ef`
- Release: `source-v2026091901`
- Formal release workflow: `ZONOE Source Release` Run `35415710535` / Run #94 — SUCCESS
- GitHub Release online-update E2E: SUCCESS

## Development lineage

- Development branch: `feature/phase20-ipa-management-v1`
- Phase 20 development CI baseline: `a246e0708fdb3c3a9650ba1f331fa39f6c938416`
- Phase 20 IPA Management Run: `35415567660` / Run #88 — SUCCESS
- Release branch was created from the green Phase 20 development baseline, then formal release metadata was prepared as version `2026091901`.

## Phase 20 completed capabilities

- 20.0: IPA management UI/menu/auth foundation.
- 20.1: persistence, task, idempotency and operation-audit foundation.
- 20.2: OpenList incremental discovery and scan planning.
- 20.3: HTTP Range IPA parser and metadata extraction.
- 20.4: persistent one-to-one IPA ↔ category binding.
- 20.5: global metadata write-back templates.
- 20.6: governance preview → confirm → execute → verify with `plan_hash` stale protection.
- 20.7: batch governance, failure queue, failed/interrupted recovery, Range metrics, bounded Retention, ignore/unignore/`ignore_until`, and high-risk permission gating.

## Verified release gates

The project continues to use the historical release model. No standalone Phase 20 release workflow or external-acceptance file is required.

Formal `ZONOE Source Release` Run #94 passed:

- PHP 7.0 lint and full historical regression: PASS.
- Source integrity / `file_sign` verification: PASS.
- Real MySQL 5.7 migration gate: PASS.
- Nine Phase 20 migrations executed twice with key-table and permission-rule verification: PASS.
- Existing Phase 19.3.1 HTTP load gate: PASS.
- Phase 20 OpenList HTTP integration: PASS.
- Phase 20 updater forced-failure rollback integration: PASS.
- Legacy updater rollback regression: PASS.
- Release metadata validation: PASS.
- Manifest-driven online-update ZIP build and payload verification: PASS.
- GitHub Release creation for `source-v2026091901`: PASS.
- Historical GitHub Release online-update E2E from the previous stable release to `2026091901`: PASS.

## Formal release assets

- Tag: `source-v2026091901`
- Target commit: `65d464b4d86f7bca9eb0ad89e96edcbf0ab5a9ef`
- `zonoe-online-update.zip`
  - size: `217993` bytes
  - GitHub asset digest: `sha256:264964f96c538d227edc65f3e2efdc0d0189596eeface832e52e5ebe02d57bd5`
- `zonoe-online-update.zip.sha256`
- `file_sign`: `f3f6e072f814d06403ce5e393967c9e2`

## Release / CI rule going forward

Keep the established project flow:

1. Each development phase uses its own CI/contracts.
2. Release metadata is prepared on a `release/**` branch.
3. Formal release always uses the existing `ZONOE Source Release` workflow.
4. `package-and-release` remains gated by the historical regression/MySQL/load jobs plus phase-specific tests required by the current release.
5. The workflow creates or refreshes `source-v${VERSION}` and then runs the existing GitHub Release online-update E2E.
6. Do not introduce a parallel formal-release workflow, manual acceptance JSON gate, or replacement release mechanism unless the project owner explicitly requests a redesign.

## Safety invariants retained

- Batch governance never moves OpenList files automatically.
- Recovery never reuses an old governance plan.
- Retention requires preview + plan hash.
- Retention never selects `running`, `failed`, `interrupted`, `queued`, or `retrying` records.
- Retention is bounded to 1000 candidate rows per table per execution.
- Ignore lifecycle batches are bounded to 100 issues.
- OpenList rename/move must reject unsafe target conflicts and preserve audit/verify semantics.

## Existing production caveat

`/authorization` remains the online-update-safe authorization lookup URL. `/license` can still be intercepted by the production Nginx LICENSE rule before PHP.
