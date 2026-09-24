# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092401`
- Active release branch: `release/2026092402-openbasedir-worker-hotfix`
- Release commit: `54b15d01d4d848e7243600a145500a865ae6fdfd`
- Current release: `source-v2026092402`
- Historical releases must not be rewritten.

## 2026092402 implementation

1. `IpaWorkerLauncher` no longer calls `is_file/is_executable` on `PHP_BINDIR/php`, fixing BaoTa `open_basedir` failures while retaining the same-site PHP 7.0 CLI selection.
2. `IpaScanService::createJob()` ensures the Scan Worker before inserting a new Job, so launcher failures no longer leave orphan `pending` jobs that block incremental scans.
3. `clearScanJobs` cancels active scan jobs/items then clears scan history; it does not delete IPA assets, OpenList sources, or `fa_category`.
4. `deleteAsset` / `clearAssets` remove IPA asset records and derived parse/binary/compare/category-binding rows; they do not delete the actual OpenList IPA file or `fa_category`.
5. Asset list exposes `source_name` + Source ID so provenance is explicit: IPA assets are created from OpenList `.ipa` scan discovery, not from software-source MySQL.
6. Disabled OpenList sources are hidden by default, with a show/hide toggle. Disable keeps configuration; delete remains a separate action.
7. New idempotent MySQL 5.7 auth migration adds permissions for the three cleanup actions.

## Release evidence

- Online Update Release Gate `35945370518`: SUCCESS.
- ZONOE Source Release `35945370658`: SUCCESS.
- GitHub Release `source-v2026092402` targets `54b15d01d4d848e7243600a145500a865ae6fdfd`.
- Release ZIP: `zonoe-online-update.zip`, size `203900`, SHA256 `5bdd7f86ec0f75c4d259eb31db74b2ffd41f1f14b6460c2d8df588f3d31d0cf2`.
- Release asset ID: `584965054`.
- CI Artifact: `zonoe-source-2026092402-online-update`, ID `10786194133`, size `196887`, digest `sha256:51416c88a6191be5ba202e473254a948fc6721c9e2acef6e02cc67ad58e16f3b`.
- Real GitHub Release online-update E2E `2401 -> 2402`: SUCCESS.

## Verification boundary

Verified: PHP 7.0 regression, MySQL 5.7 migration/idempotence, permission rules, HTTP concurrency/load gate, release packaging, GitHub Release publication, and real Release online-update E2E.

Not yet verified: actual user's BaoTa server after installing 2026092402, real Worker process launch under that site's `proc_open`/open_basedir configuration, UI cleanup buttons against production data, and full OpenList scan completion.

## Production verification sequence

1. Online update from 2401 to 2402.
2. Click `清理全部扫描任务` to remove the stale 2401 pending Job created before the open_basedir failure.
3. Confirm disabled OpenList entries are hidden by default; use `显示已停用` to reveal them.
4. Click one enabled source's `全量扫描`.
5. Expected: no open_basedir error; Worker starts automatically and Job transitions `pending -> running -> completed`.
6. Confirm IPA assets show OpenList source name/ID and deletion controls work without modifying `fa_category`.
7. If Worker startup still fails, inspect `runtime/log/ipa_scan_worker.log` and PHP 7.0 `disable_functions` for `proc_open`.

Do not rewrite historical `source-v2026092206`, `source-v2026092207`, or `source-v2026092401` releases.
