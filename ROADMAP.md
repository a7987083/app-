# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092403`
- Current release branch: `release/2026092404-inline-parse-refresh-source-fix`
- Current release commit: `23bd77bbff667a2c6e304a76dd2c62c1394f3cd8`
- Current release: `source-v2026092404`
- Historical 2206/2207/2401/2402/2403 releases must not be rewritten.

## 2026092404 — FPM inline parse / live refresh / software-source response fix

- [x] 扫描与解析统一到 PHP-FPM 当前进程内消费模型；Web 操作不再依赖 CLI `ipa:parse-worker` 或子进程。
- [x] 开启/继续自动解析时立即调度 Parse consumer；扫描结束且 `parse_enabled=1` 时继续解析 discovered IPA。
- [x] 重试/重新解析在自动解析开启时立即唤醒 Parse consumer；暂停状态下不擅自恢复自动解析。
- [x] IPA 中心增加手动刷新，并在页面可见时每 5 秒自动刷新扫描任务和 IPA 资产状态。
- [x] 修复软件源保存/测试成功响应被 broad catch 捕获为 `think\exception\HttpResponseException` 的问题。
- [x] 软件源继续允许先保存配置；连接正确性由“测试连接”单独判断，失败返回 PDO/MySQL 原始错误。
- [x] PHP 7.0 regression — SUCCESS。
- [x] MySQL 5.7 migration/idempotence — SUCCESS。
- [x] HTTP concurrency/load gate — SUCCESS。
- [x] ZONOE Source Release Run `35964382173` — SUCCESS。
- [x] GitHub Release `source-v2026092404` 已发布。
- [x] Real GitHub Release online-update E2E (`2403 -> 2404`) — SUCCESS。
- [x] Final IPA Online Update Release Gate Run `35964464124` — SUCCESS。
- [x] CI Artifact `zonoe-source-2026092404-online-update` 已生成。
- [ ] 真实 BaoTa 更新到 2404 后验证：开启解析无需 CLI 即 `discovered -> parsing -> parsed`。
- [ ] 验证手动刷新与 5 秒自动刷新能持续显示扫描/解析进度。
- [ ] 验证软件源正确配置保存不再误报 HttpResponseException；错误配置可保存，但测试连接应返回真实 PDO/MySQL 错误。

## 保留验证项

- [ ] active scan -> full scan 时旧 job cancelled、新 full job 正常完成。
- [ ] 清空解析保留 IPA discovery/OpenList/source/`fa_category`。
- [ ] IPA 资产删除/清空在真实生产数据上不修改 `fa_category`，OpenList 文件仍可再次扫描发现。
- [ ] 观察大型 OpenList 树与批量解析时 PHP-FPM worker 占用情况。

## Next Task

真实 BaoTa 从 `source-v2026092403` 在线更新到 `source-v2026092404`。验证一次 OpenList 扫描后自动解析、一次暂停/继续解析、一次解析失败重试，以及软件源正确/错误配置的保存与测试连接。预期整个 Web 执行链无需 SSH、CLI Worker 或子进程。
