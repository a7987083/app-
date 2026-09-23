# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Stable release branch: `release/2026092206-ipa-controls-regression-hotfix`
- Stable release: `source-v2026092206`
- Active development branch: `release/2026092207-ipa-controls-regression-fix`
- Baseline commit: `cd719391f25713224dab4a1301f9e551b0cde969`
- Code HEAD: `51518a17b7338670d63ab68b925caa82c9010904`
- Validation PR: `#22` (draft; do not merge until runtime/manual verification is complete)

## 2207 root causes and implementation

1. Full scan regression
   - Previous `IpaScanService::createJob()` rejected both incremental and full when a same-source job was pending/running.
   - 2207 keeps incremental mutual exclusion but makes full scan cancel active same-source jobs/items before creating a new full job.
   - Running workers check job cancellation around network/page processing and periodically while consuming rows.

2. Parse rate-limit regression
   - `IpaOpsSettings` rate window/hour/day limiting was introduced on 2026-09-22.
   - 2207 keeps persisted fields/counters for compatibility but `allowed` now depends only on `parse_enabled`.

3. Worker-status UI regression
   - Worker status/rate-limit UI introduced on 2026-09-22 is removed from IPA Center.
   - `WorkerState` remains an internal mechanism for orphaned `parsing` recovery; it is not shown as a new UI status panel.

4. Pause/resume response exception regression
   - Targeted controller actions had `$this->success()` inside broad `catch (\Throwable)` / `catch (\Exception)` regions.
   - Framework success responses use `HttpResponseException`; 2207 moves success calls outside the targeted try/catch regions.

5. Clear-parse boundary
   - No broad deletion was added.
   - Clear parse preserves OpenList/IPA discovery rows and `fa_category`; it clears parse binaries, compare results, parse attempts and resets parsed/failed IPA assets to discovered.

## Verification evidence

- PR `#22` has only the intended 2207 delta against the 2206 hotfix base before this documentation sync.
- `IPA Data Center CI` Run `35919441555`: SUCCESS.
- `Regression Checks` Run `35919441640`: SUCCESS.
- `Phase14 Production Hardening` Run `35919441698`: SUCCESS.
- PHP 7.0, ThinkPHP CLI registration, iPhoneOS SDK arm64 compile, OpenList integration, MySQL 5.7 schema/concurrency/100k scale, scan-safety and operations contracts passed.
- IPA CI artifact: none.

## Verification boundary

Verified: source diff, branch/baseline, PHP 7.0 syntax/contracts, CI integration contracts, iOS verifier compilation.

Not verified: actual BaoTa deployment, browser click path, live OpenList scan replacement timing, real production dataset, manual pause/resume UI result, production clear-parse result.

## Next task

Deploy this branch to a non-production/production-test BaoTa instance and manually exercise:

1. Start incremental/full scan, then trigger full scan during active scan; confirm old job becomes `cancelled` and new full job completes.
2. Pause and resume parsing; confirm no `think\exception\HttpResponseException` reaches the UI.
3. Parse more than the old 5-minute quota and confirm claims continue while `parse_enabled=1`.
4. Clear parse after pause; confirm IPA discovery rows and `fa_category` stay unchanged while parse-derived rows are cleared.

Do not rewrite or replace the historical `source-v2026092206` release.
