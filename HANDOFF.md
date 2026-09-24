# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092207`
- Active release branch: `release/2026092401-ipa-worker-autostart-hotfix`
- Release commit: `050dbae659ae48ba2184063361f1b91fb959b6d5`
- Current release: `source-v2026092401`
- Worker hotfix PR: `#23` (draft)

## 2026092401 implementation

1. `IpaCenter::startScan()` creates a job then calls `IpaWorkerLauncher::ensureScanWorker()`.
2. Launcher uses `PHP_BINDIR/php` so BaoTa PHP 7.0 FPM launches PHP 7.0 CLI rather than shell `/usr/bin/php` (observed PHP 8.2).
3. Worker is spawned from FPM and inherits the existing `PHP_IPA_SERVER_SECRET`, retaining compatibility with already encrypted OpenList `token_ciphertext`.
4. `WorkerState` gets a `starting` heartbeat before spawn to reduce duplicate workers from rapid repeated clicks.
5. Worker output is written to `runtime/log/ipa_scan_worker.log`.
6. No new SQL migration; 2026092205 schema and 2207 scan/control semantics remain authoritative.

## Release evidence

- Online Update Release Gate `35938782889`: SUCCESS.
- ZONOE Source Release `35938782890`: SUCCESS.
- GitHub Release `source-v2026092401` targets `050dbae659ae48ba2184063361f1b91fb959b6d5`.
- Release ZIP: `zonoe-online-update.zip`, size `202039`, SHA256 `0e230d19e5803f800482678da4c4de23b17acc2bdaa09625e2e979630e1a4f73`.
- CI Artifact: `zonoe-source-2026092401-online-update`, ID `10784416382`, size `194805`, digest `sha256:6cfba91e01f2346ce2f601f540a73678d3209f6bdf3bcd30ab8825bedbdd964d`.
- Real GitHub Release online-update E2E `2207 -> 2401`: SUCCESS.

## Verification boundary

Verified: PHP 7.0 syntax/regression, MySQL 5.7 migration/contracts, Worker claim contention, OpenList HTTP contracts, scan safety contracts, package build, GitHub Release publication, and online-update E2E.

Not yet verified: actual user's BaoTa server after installing 2026092401, FPM `proc_open` availability on that server, real OpenList scan completing from UI, and the remaining 2207 browser/runtime scenarios.

## Next task

Install `source-v2026092401` via the existing online updater on the BaoTa server. Then click Full Scan once. Expected path: job created -> Worker auto-started with site PHP/FPM environment -> pending item claimed -> job running/completed. If it remains pending, inspect `runtime/log/ipa_scan_worker.log` and PHP 7.0 `disable_functions` for `proc_open`.

Do not rewrite historical `source-v2026092206` or `source-v2026092207` releases.
