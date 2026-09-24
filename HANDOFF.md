# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092402`
- Active release branch: `release/2026092403-openlist-inline-scan`
- Release commit: `987e62f28b7c2f70fb669c75f8e9e2c7598e6aab`
- Current release: `source-v2026092403`
- Historical releases must not be rewritten.

## 2026092403 implementation

1. OpenList Web scans no longer create a separate PHP CLI process.
2. `IpaWorkerLauncher::ensureScanWorker()` schedules a shutdown consumer in the current PHP-FPM process; under FPM it first calls `fastcgi_finish_request()` so the browser receives the response, then drains the existing scan queue.
3. Queue execution reuses `IpaScanService::claimOne()`, `processItem()`, `failItem()` and existing job reconciliation. Full-scan restart, incremental mutual exclusion, cancellation, retry backoff and progress counters remain intact.
4. Token decrypt occurs in the same FPM runtime that already has `PHP_IPA_SERVER_SECRET`; no CLI environment inheritance is required.
5. Existing `php think ipa:worker` remains an optional external queue consumer, but is not required for UI scans.
6. 2402 management controls remain: clear scan jobs, delete/clear IPA assets and derived rows, asset OpenList provenance, disabled-source filtering. These operations do not modify `fa_category` or delete actual OpenList IPA files.

## Release evidence

- Final IPA Online Update Release Gate `35956707429`: SUCCESS.
- ZONOE Source Release `35956499625`: SUCCESS.
- PHP 7.0 regression: SUCCESS.
- MySQL 5.7 migration: SUCCESS.
- HTTP concurrency/load gate: SUCCESS.
- Package/Release: SUCCESS.
- Real GitHub Release online-update E2E `2402 -> 2403`: SUCCESS.
- GitHub Release `source-v2026092403` targets `987e62f28b7c2f70fb669c75f8e9e2c7598e6aab`.
- Release ZIP: `zonoe-online-update.zip`, size `203965`, SHA256 `0ef18ec9acdbeeeb43446e21989ceeb68f2b7c4aa15ed2828c7c8a9a4f9b359b`.
- Release asset ID: `585158312`.
- CI Artifact: `zonoe-source-2026092403-online-update`, ID `10791135406`, size `196963`, digest `sha256:1416beff9b49b43ba52ac6681ec90665f0feec87518651e8144c04148e7e3e88`.

## Verification boundary

Verified in CI/release: PHP 7.0 syntax/regression, MySQL 5.7 migration/idempotence, HTTP load gate, release package generation, GitHub Release publication, 2402 -> 2403 real online-update E2E, and a runtime contract preventing the Web scan launcher from returning to the child-process implementation.

Not yet verified: actual user's BaoTa PHP-FPM executing the shutdown consumer through a complete real OpenList tree; production cleanup controls against real data; remaining 2207 browser/runtime scenarios.

## Production verification sequence

1. Online update from 2402 to 2403.
2. Click `清理全部扫描任务` once to remove stale scan jobs left from 2401/2402 failures.
3. Confirm disabled OpenList entries are hidden by default and `显示已停用` reveals them.
4. Click one enabled source's `全量扫描`.
5. Expected: request returns normally; no PHP CLI process creation is attempted; the FPM process consumes the queue and the Job moves `pending -> running -> completed`.
6. Confirm IPA assets show OpenList source name/ID.
7. Verify asset delete/clear does not modify `fa_category`; if the actual OpenList IPA remains, a later scan should recreate the asset.

Do not restore the 2401/2402 Web -> CLI launcher merely to solve scan execution. OpenList scan execution in 2403 is intentionally in-process.
