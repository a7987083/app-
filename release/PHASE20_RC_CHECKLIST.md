# Phase 20 Release Candidate Closeout

## Code / CI baseline

- Development branch: `feature/phase20-ipa-management-v1`
- Phase 20.0 ~ 20.7 code complete.
- Phase 20 contract workflow must be green before an RC is cut.
- Do not change `VERSION`, `public/update/ver.txt`, `ver.json`, or the formal `release/RELEASE_NOTES.md` until the RC version is explicitly selected.

## Online-update package contract

The RC online-update package must contain both `program/` and `mysql/` payloads.

### Program payload

`release/online-update-files.txt` must contain the Phase 20 controllers, commands, views, libraries, parser helper, JavaScript, and the Phase 20 install SQL source files.

### MySQL payload

`tools/build_online_update.php` packages the canonical Phase 20 install SQL directly from `application/admin/command/Install/` under ordered names:

1. `2026091901_phase20_ipa_foundation.sql`
2. `2026091902_phase20_ipa_center.sql`
3. `2026091903_phase20_ipa_scan.sql`
4. `2026091904_phase20_ipa_parser.sql`
5. `2026091905_phase20_ipa_binding.sql`
6. `2026091906_phase20_ipa_writeback.sql`
7. `2026091907_phase20_ipa_governance.sql`
8. `2026091908_phase20_ipa_production.sql`
9. `2026091909_phase20_ipa_lifecycle.sql`

The canonical SQL source remains single-copy under `application/admin/command/Install/`; do not maintain divergent release SQL copies.

## Pre-production E2E gate

Before RC promotion, use a controlled OpenList + MySQL 5.7 environment with a verified backup and restore path.

- [ ] Install Phase 20 migrations twice and confirm idempotency/no destructive duplicate behavior.
- [ ] Configure an OpenList source and run full scan.
- [ ] Parse representative IPA files and compare Range metrics against observed parser traffic.
- [ ] Bind representative IPA metadata to categories.
- [ ] Run governance refresh and inspect anomalies.
- [ ] Batch preview low-risk governance items; verify `batch_hash` and per-item `plan_hash` behavior.
- [ ] Apply a controlled low-risk batch and confirm post-apply verification/audit rows.
- [ ] Execute one explicit single-item OpenList path mutation; confirm no overwrite and metadata/binding path synchronization.
- [ ] Force one failed/interrupted governance operation and verify retry creates a new preview/idempotency path and supersedes the old operation only after success.
- [ ] Exercise ignore, unignore, and expiry sweep.
- [ ] Run Retention preview only; inspect candidate IDs and protected states.
- [ ] Restore from backup in the test environment before allowing any production Retention apply.
- [ ] Apply bounded Retention and verify only eligible success/cancelled or success/superseded history is removed.
- [ ] Verify real FastAdmin permission groups: hidden controls and API denial must agree.

## Rollback gate

- [ ] Confirm updater file backup is created before SQL/program mutation.
- [ ] Confirm database backup is restorable for Phase 20 schema/data changes.
- [ ] Record the updater backup directory from the E2E run.
- [ ] Verify failed package installation invokes automatic rollback.
- [ ] Verify application remains usable after rollback.

## RC promotion

Only after the E2E and rollback gates pass:

- [ ] Select the formal RC version.
- [ ] Update `VERSION`, `public/update/ver.txt`, and `ver.json` together.
- [ ] Replace `release/RELEASE_NOTES.md` with Phase 20 RC notes using the selected version.
- [ ] Update `PHASE13_RELEASE.txt` version metadata.
- [ ] Run the formal release workflow including PHP 7.0 regression, MySQL 5.7, package validation, SHA256, and online-update E2E.
- [ ] Create the RC tag/release only from a green release run.
