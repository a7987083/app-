# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092403`
- Active release branch: `release/2026092404-inline-parse-refresh-source-fix`
- Release commit: `23bd77bbff667a2c6e304a76dd2c62c1394f3cd8`
- Current release: `source-v2026092404`
- Historical releases must not be rewritten.

## 2026092404 implementation

1. Scan and parse Web execution now share the PHP-FPM in-process queue-consumer model. No UI action requires Web-side child-process creation.
2. `IpaWorkerLauncher::ensureParseWorker()` schedules Parse work after the response; `IpaOpsSettings::save()` invokes it when `parse_enabled=1`.
3. The Scan consumer continues into parsing when auto-parse is enabled, so newly discovered IPA records can move `discovered -> parsing -> parsed/parse_failed` without a CLI parse worker.
4. Parse execution reuses `IpaParserService`, encrypted OpenList token handling, attempt records, comparison refresh and existing asset statuses. The CLI `ipa:parse-worker` remains optional compatibility tooling only.
5. IPA Center has a manual refresh button and 5-second visible-page refresh for scan jobs and IPA assets. Parse pause/resume controls update in place.
6. `IpaSourceCenter` no longer catches normal FastAdmin success-response `HttpResponseException`. Correct saves report success; actual service errors report failure.
7. Software-source configuration remains save-first/test-separately: invalid MySQL credentials may be stored, while `测试连接` is authoritative and surfaces the PDO/MySQL error.
8. 2402/2403 management behavior remains: scan cleanup, asset delete/clear, source provenance, disabled-source filtering; none of these operations modifies `fa_category` or deletes actual OpenList IPA files.

## Release evidence

- Final Online Update Release Gate `35964464124`: SUCCESS.
- ZONOE Source Release `35964382173`: SUCCESS.
- PHP 7.0 regression: SUCCESS.
- MySQL 5.7 migration: SUCCESS.
- HTTP concurrency/load gate: SUCCESS.
- Package/Release: SUCCESS.
- Real GitHub Release online-update E2E `2403 -> 2404`: SUCCESS.
- GitHub Release `source-v2026092404` targets `23bd77bbff667a2c6e304a76dd2c62c1394f3cd8`.
- Release ZIP: `zonoe-online-update.zip`, size `205268`, SHA256 `88e278c3da6765e49373af56746e61866c5dad48caf0d6b4b35b06caab22d474`.
- Release asset ID: `585309359`.
- CI Artifact: `zonoe-source-2026092404-online-update`, ID `10793492573`, size `198556`, digest `sha256:60a91d6335a5f5145b5523b0d6e1d06004c2b6f3b20b0235b9929697cff131f2`.

## Verification boundary

Verified in CI/release: PHP 7.0 syntax/regression, MySQL 5.7, source integrity, package publication, HTTP load gate, final 2404 runtime contracts, and real 2403 -> 2404 GitHub Release online-update E2E.

Not yet verified on the user's BaoTa host: complete live FPM parse of real IPA data, UI refresh timing under production load, correct/incorrect software-source save + test behavior, and FPM capacity during a large combined scan+parse workload.

## Production verification sequence

1. Online update `source-v2026092403 -> source-v2026092404`.
2. Run/observe one OpenList scan with auto-parse enabled; expect discovered IPA to proceed to parsing/parsed without SSH or CLI workers.
3. Pause parsing, confirm no new IPA is claimed; resume, confirm parsing starts again.
4. Use manual refresh and observe the 5-second automatic table refresh without full-page reload.
5. Save one valid software-source configuration: expected save success, no `HttpResponseException` error; test connection should succeed.
6. Save one deliberately invalid test configuration if desired: expected save itself can succeed, while test connection should return the real PDO/MySQL error.
7. Continue verifying cleanup operations do not modify `fa_category`.

Do not restore the 2401/2402 Web -> CLI launcher to solve scan or parse execution.
