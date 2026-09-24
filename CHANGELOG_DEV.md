# Development Changelog

## 2026-09-24 — Release 2026092404

Baseline: `source-v2026092403`.

### Production issues confirmed

- 2403 Web 扫描已改为 FPM 内消费，但“开启/继续解析”仍只保存 `parse_enabled=1`，真正解析仍留在 CLI `IpaParseWorker`，因此没有 Parse Worker 时页面一直停在“待解析”。
- IPA 中心缺少页面内刷新能力，扫描/解析状态需要整页刷新才能看到变化。
- `IpaSourceCenter::add/edit/saveSource/testSource/deleteSource` 将正常 `$this->success()` 放在 broad `catch (\Throwable)` 内；FastAdmin 成功响应使用 `think\exception\HttpResponseException` 中断，导致数据已经保存成功却仍提示“软件源保存失败”。

### Fix

- `IpaWorkerLauncher` 统一调度 Scan / Parse 两条 PHP-FPM in-process consumer；Web 操作不再要求 CLI `ipa:parse-worker` 常驻，也不创建子进程。
- `IpaOpsSettings::save()` 在 `parse_enabled=1` 时立即调度 Parse consumer；扫描 consumer 完成后若自动解析开启也会继续消费 discovered IPA。
- Parse consumer 复用现有 `IpaParserService`、SecretBox、parse attempt、compare service 和 asset 状态语义；失败落 `parse_failed`，暂停阻止领取下一条。
- 前端新增手动刷新并每 5 秒自动刷新扫描任务/IPA 资产；暂停/继续按钮可在原页面即时切换。
- 软件源 Controller 将 success 响应移到 catch 外，只捕获真实业务异常；“测试连接”失败显示原始 PDO/MySQL 异常。
- 软件源保存语义不变：可先保存错误/未连通配置，是否正确由“测试连接”独立判断。
- Release commit: `23bd77bbff667a2c6e304a76dd2c62c1394f3cd8`.

### Verification and release

- Final IPA Online Update Release Gate Run `35964464124` — SUCCESS.
- ZONOE Source Release Run `35964382173` — SUCCESS.
- PHP 7.0 regression — SUCCESS.
- MySQL 5.7 migration — SUCCESS.
- Phase 19.3.1 HTTP concurrency/load gate — SUCCESS.
- Package/Release — SUCCESS.
- Real GitHub Release online-update E2E (`source-v2026092403 -> source-v2026092404`) — SUCCESS.
- Release `source-v2026092404` targets `23bd77bbff667a2c6e304a76dd2c62c1394f3cd8`.
- Release ZIP size `205268` bytes; SHA256 `88e278c3da6765e49373af56746e61866c5dad48caf0d6b4b35b06caab22d474`.
- Release asset ID `585309359`.
- CI Artifact `zonoe-source-2026092404-online-update`: ID `10793492573`, size `198556` bytes, digest `sha256:60a91d6335a5f5145b5523b0d6e1d06004c2b6f3b20b0235b9929697cff131f2`.
- Actual BaoTa runtime after installing 2026092404: NOT YET VERIFIED.

## 2026-09-24 — Release 2026092403

- Replaced Web -> CLI OpenList scan launcher with PHP-FPM in-process queue consumption.
- Removed required scan-path dependency on `proc_open`/`nohup`/external PHP CLI.
- Source Release `35956499625`, final Gate `35956707429`, and real `2402 -> 2403` online-update E2E all passed.

## 2026-09-24 — Release 2026092402

- Fixed external PHP CLI `is_file` probe under BaoTa `open_basedir`.
- Added scan-job cleanup, IPA asset single/all deletion, OpenList source provenance, and disabled-source filtering.

## 2026-09-24 — Release 2026092401

- Added first Web-side Scan Worker automatic launcher; production testing later proved the child-process approach unsuitable.

## 2026-09-24 — Release 2026092207

- Restored full-scan restart semantics; removed parse quota enforcement; fixed framework response-chain handling; clear parse preserves discovery/OpenList/source/`fa_category`.

## 2026-09-23 — Release 2026092206

- Stable historical regression hotfix. Do not rewrite historical releases.
