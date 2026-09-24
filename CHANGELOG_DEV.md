# Development Changelog

## 2026-09-24 — Release 2026092403

Baseline: `source-v2026092402`.

### Production issue confirmed

- Real BaoTa 2402 no longer failed at the external PHP binary probe, but Web-side Worker creation still failed with `无法创建 IPA 扫描 Worker 进程`.
- Root cause was architectural: 2401/2402 introduced a Web -> CLI child-process launcher even though OpenList scanning itself only requires HTTP API calls and the older project did not require that launcher.
- Reference project `a7987083/ipaxiazaizhan-` (`feature/baota-native-deploy-v1`) confirmed manual OpenList scans return immediately and continue inside the API process; child processes are only used there for a separate Python IPA parser.

### Fix

- Reworked `IpaWorkerLauncher` into a PHP-FPM in-process queue consumer.
- `ensureScanWorker()` registers shutdown work instead of launching another PHP process.
- Under FPM, `fastcgi_finish_request()` flushes the HTTP response first, then the same process drains `ipa_scan_item` using existing `claimOne/processItem/failItem` semantics.
- Existing database queue, full-scan restart, incremental mutual exclusion, retry backoff, cancellation and progress semantics are retained.
- OpenList Token decrypt runs in the same FPM environment that already owns the production `PHP_IPA_SERVER_SECRET`.
- The optional CLI command `php think ipa:worker` remains available, but Web scans no longer require a CLI Worker.
- 2402 cleanup/delete/source-filter features are retained unchanged.
- Release commit: `987e62f28b7c2f70fb669c75f8e9e2c7598e6aab`.

### Verification and release

- ZONOE Source Release Run `35956499625` — SUCCESS.
- PHP 7.0 regression — SUCCESS.
- MySQL 5.7 migration — SUCCESS.
- Phase 19.3.1 HTTP concurrency/load gate — SUCCESS.
- Package/Release — SUCCESS.
- Real GitHub Release online-update E2E (`source-v2026092402 -> source-v2026092403`) — SUCCESS.
- Final IPA Online Update Release Gate Run `35956707429` — SUCCESS, including the PHP 7.0 runtime contract that forbids reintroducing the child-process launcher path.
- Release `source-v2026092403` targets `987e62f28b7c2f70fb669c75f8e9e2c7598e6aab`.
- Release ZIP size `203965` bytes; SHA256 `0ef18ec9acdbeeeb43446e21989ceeb68f2b7c4aa15ed2828c7c8a9a4f9b359b`.
- CI Artifact `zonoe-source-2026092403-online-update`: ID `10791135406`, size `196963` bytes, digest `sha256:1416beff9b49b43ba52ac6681ec90665f0feec87518651e8144c04148e7e3e88`.
- Actual BaoTa runtime after installing 2026092403: NOT YET VERIFIED.

## 2026-09-24 — Release 2026092402

- Fixed external PHP CLI `is_file` probe under BaoTa `open_basedir`.
- Added scan-job cleanup, IPA asset single/all deletion, OpenList source provenance, and disabled-source filtering.
- Online Update Gate `35945370518` and Source Release `35945370658` — SUCCESS.
- Real GitHub Release online-update E2E (`2401 -> 2402`) — SUCCESS.
- Production follow-up showed the Web -> CLI launcher architecture itself remained unsuitable; replaced by 2403.

## 2026-09-24 — Release 2026092401

- Added the first Web-side Scan Worker automatic launcher to address PHP 7.0 FPM vs shell PHP 8.2 and secret inheritance problems.
- Online Update Gate `35938782889` and Source Release `35938782890` — SUCCESS.
- Real GitHub Release online-update E2E (`2207 -> 2401`) — SUCCESS.
- Production testing later proved the child-process launcher should not be part of the OpenList scan path.

## 2026-09-24 — Release 2026092207

- Restored full-scan restart semantics while incremental remained mutually exclusive.
- Removed parse quota enforcement and Worker-status/quota UI.
- Fixed framework `HttpResponseException` response-chain handling.
- Clear parse preserves IPA discovery/OpenList/source/`fa_category`.
- Online Update Gate `35929007415` and Source Release `35930081424` — SUCCESS.

## 2026-09-23 — Release 2026092206

- Stable historical regression hotfix. Release commit `2cf0e030eb2db3a4aa079462c7a4399720214d1b`.
- Do not rewrite historical releases.
