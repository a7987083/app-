# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092402`
- Current release branch: `release/2026092403-openlist-inline-scan`
- Current release commit: `987e62f28b7c2f70fb669c75f8e9e2c7598e6aab`
- Current release: `source-v2026092403`
- Historical 2206/2207/2401/2402 releases must not be rewritten.

## 2026092403 — OpenList FPM in-process scan hotfix

- [x] 撤销 2401/2402 的 Web -> CLI 子进程扫描启动链。
- [x] OpenList 扫描不再依赖 `proc_open`、`nohup`、`/dev/null` 或 `PHP_BINDIR/php`。
- [x] 保留 `ipa_scan_job / ipa_scan_item` 队列及全量重扫、增量互斥、失败重试语义。
- [x] HTTP 响应结束后由当前 PHP-FPM 进程继续 `claimOne -> processItem` 消费扫描队列。
- [x] PHP-FPM 环境直接使用既有 `PHP_IPA_SERVER_SECRET` 解密 OpenList Token，不再存在 FPM/CLI 密钥环境分裂。
- [x] 保留 2402 的扫描任务清理、IPA 资产单条/全部删除、OpenList 来源显示、停用源筛选。
- [x] PHP 7.0 regression — SUCCESS。
- [x] MySQL 5.7 migration/idempotence — SUCCESS。
- [x] HTTP concurrency/load gate — SUCCESS。
- [x] ZONOE Source Release Run `35956499625` — SUCCESS。
- [x] GitHub Release `source-v2026092403` 已发布。
- [x] Real GitHub Release online-update E2E (`2402 -> 2403`) — SUCCESS。
- [x] IPA Online Update Release Gate Run `35956707429` — SUCCESS。
- [x] CI Artifact `zonoe-source-2026092403-online-update` 已生成。
- [ ] 真实 BaoTa 更新到 2403 后，清理 2402 遗留扫描任务，再点全量扫描，确认无需 SSH/CLI Worker 即进入 running/completed。

## 保留验证项

- [ ] active scan -> full scan 时旧 job cancelled、新 full job 正常完成。
- [ ] 暂停/继续不再显示 `think\exception\HttpResponseException`。
- [ ] 自动解析不再受旧 5 分钟/小时/每日配额阻断。
- [ ] 清空解析保留 IPA discovery/OpenList/source/`fa_category`。
- [ ] IPA 资产删除/清空在真实生产数据上不修改 `fa_category`，OpenList 文件仍可再次扫描发现。

## Next Task

真实 BaoTa 从 `source-v2026092402` 在线更新到 `source-v2026092403`。更新后先点击“清理全部扫描任务”移除 2402 故障期间遗留的 pending/running 历史，再对启用的 OpenList 数据源执行一次全量扫描。预期：页面正常返回；无需 `proc_open`、无需 SSH 手工启动 Worker；FPM 同进程后台消费队列，任务进入 `running -> completed`，IPA 资产显示正确 OpenList 来源。
