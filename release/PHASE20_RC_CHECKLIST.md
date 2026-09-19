# Phase 20 Release Candidate Closeout

## Code / CI baseline

- Development branch: `feature/phase20-ipa-management-v1`
- Phase 20.0 ~ 20.7 code complete.
- Verified integration HEAD: `3dc03a314c9f3f3ae8811025d3d9274f71558421`.
- Verified workflow: Run #70 / `35412127806` — SUCCESS.
- RC artifact: `phase20-rc-35412127806`.
- Do not change `VERSION`, `public/update/ver.txt`, `ver.json`, or the formal `release/RELEASE_NOTES.md` until the external-environment acceptance is complete and the RC version is explicitly selected.

## Online-update package contract

The RC online-update package must contain both `program/` and `mysql/` payloads.

### Program payload

`release/online-update-files.txt` contains the Phase 20 controllers, commands, views, libraries, parser helper, JavaScript, and the Phase 20 install SQL source files.

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

## CI / isolated integration gates — verified

- [x] Full Phase 20 / Phase 20.7 contract suite passes.
- [x] Build the real online-update ZIP on PHP 7.0 with ZipArchive.
- [x] Verify package SHA256.
- [x] Verify required Phase 20 program files are present in `program/`.
- [x] Verify all nine ordered Phase 20 migrations are present in `mysql/`.
- [x] Start a real MySQL 5.7 service in CI.
- [x] Install all Phase 20 migrations twice and confirm idempotency/no duplicate auth-rule names.
- [x] Verify key Phase 20 tables and high-risk auth rules after migration.
- [x] Run OpenList client through real loopback HTTP for health/list/get/rename/move.
- [x] Verify Authorization propagation, recursive IPA discovery, and client-side unsafe rename rejection.
- [x] Force an updater failure after program files are copied and verify automatic rollback.
- [x] Verify overwritten files are restored, update-created files are removed, old version metadata remains, backup/database dump are retained, and rollback success is emitted.
- [x] Run legacy update rollback regression.
- [x] Upload the built RC ZIP + SHA256 as a GitHub Actions artifact.

## External pre-production acceptance — required before RC promotion

Use a controlled target deployment with a verified backup/restore path. These items must not be marked complete from CI mocks alone.

- [ ] Configure the intended OpenList endpoint and run a full scan.
- [ ] Parse representative real IPA files and compare Range metrics against observed HTTP Range traffic.
- [ ] Bind representative IPA metadata to real application categories.
- [ ] Run governance refresh and inspect real anomalies.
- [ ] Batch preview low-risk governance items; verify `batch_hash` and per-item `plan_hash` behavior against persisted data.
- [ ] Apply a controlled low-risk batch and confirm post-apply verification/audit rows.
- [ ] Execute one explicitly approved real OpenList path mutation; confirm no overwrite and metadata/binding path synchronization.
- [ ] Force one failed/interrupted governance operation and verify retry creates a new preview/idempotency path and supersedes the old operation only after success.
- [ ] Exercise ignore, unignore, and expiry sweep on persisted data.
- [ ] Run Retention preview only; inspect candidate IDs and protected states.
- [ ] Restore from backup in the controlled environment before allowing any Retention apply.
- [ ] Apply bounded Retention and verify only eligible historical rows are removed.
- [ ] Verify real FastAdmin permission groups: hidden controls and API denial must agree.

## External rollback / usability gate

- [ ] Confirm the target updater creates its file/database backup before mutation.
- [ ] Record the updater backup directory from the controlled deployment drill.
- [ ] Restore the deployment backup and verify application usability.
- [ ] Perform one controlled failed-install drill on the target deployment and confirm rollback leaves the application usable.

## RC promotion

Only after all external acceptance and rollback gates pass:

- [ ] Select the formal RC version.
- [ ] Update `VERSION`, `public/update/ver.txt`, and `ver.json` together.
- [ ] Replace `release/RELEASE_NOTES.md` with Phase 20 RC notes using the selected version.
- [ ] Update `PHASE13_RELEASE.txt` version metadata.
- [ ] Run the formal release workflow including PHP 7.0 regression, MySQL 5.7, Phase 20 package validation, SHA256, and online-update E2E.
- [ ] Create the RC tag/release only from a green release run.
