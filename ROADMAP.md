# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092207`
- Current release branch: `release/2026092401-ipa-worker-autostart-hotfix`
- Current release commit: `050dbae659ae48ba2184063361f1b91fb959b6d5`
- Current release: `source-v2026092401`
- Worker fix PR: `#23` (draft, base = 2207 branch)
- Historical 2206/2207 releases must not be rewritten.

## 2026092401 — IPA Scan Worker production hotfix

- [x] 修复宝塔站点 PHP 7.0、SSH 默认 PHP 8.2 导致 `php think ipa:worker` Fatal 的运行时错配。
- [x] 扫描任务创建后自动检查/启动 Scan Worker。
- [x] 自动使用 `PHP_BINDIR/php`，与当前 PHP-FPM 使用同一 PHP 安装。
- [x] 自动启动 Worker 继承 FPM 环境，继续使用既有 `PHP_IPA_SERVER_SECRET` 解密 OpenList Token。
- [x] 增加 `starting` 心跳，降低连续点击重复拉起 Worker 的风险。
- [x] 新增 `IpaWorkerLauncher.php` 并纳入在线更新清单。
- [x] IPA Online Update Release Gate Run `35938782889` — SUCCESS。
- [x] ZONOE Source Release Run `35938782890` — SUCCESS。
- [x] GitHub Release `source-v2026092401` 已发布。
- [x] Real GitHub Release online-update E2E (`2207 -> 2401`) — SUCCESS。
- [x] CI Artifact `zonoe-source-2026092401-online-update` 已生成。
- [ ] 真实 BaoTa 2207 -> 2401 在线更新后，点击全量扫描确认 job 从 pending 进入 running/completed。
- [ ] 检查 `runtime/log/ipa_scan_worker.log` 无 PHP/secret/进程拉起错误。

## 保留的 2207 验证项

- [ ] active scan -> full scan 时旧 job cancelled、新 full job 正常完成。
- [ ] 暂停/继续不再显示 `think\exception\HttpResponseException`。
- [ ] 自动解析不再受旧 5 分钟/小时/每日配额阻断。
- [ ] 清空解析保留 IPA discovery/OpenList/source/`fa_category`。

## Next Task

在真实 BaoTa 服务器执行 `source-v2026092207 -> source-v2026092401` 在线更新，然后点击一次全量扫描。预期不再需要 SSH 手工启动 Worker；任务应自动被领取。若失败，优先检查 `runtime/log/ipa_scan_worker.log` 和 PHP `disable_functions` 中是否禁用了 `proc_open`。
