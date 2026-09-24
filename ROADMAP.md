# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092401`
- Current release branch: `release/2026092402-openbasedir-worker-hotfix`
- Current release commit: `54b15d01d4d848e7243600a145500a865ae6fdfd`
- Current release: `source-v2026092402`
- Historical 2206/2207/2401 releases must not be rewritten.

## 2026092402 — open_basedir / IPA management hotfix

- [x] 修复宝塔 `open_basedir` 阻止 `is_file(/www/server/php/70/bin/php)` 导致 Scan Worker 自动启动失败。
- [x] 保留 `PHP_BINDIR/php` 同版本启动方案，但不再对站点目录外 PHP CLI 做 `is_file/is_executable` 探测。
- [x] 在创建扫描 Job 前先完成 Worker 启动预检；启动失败不再留下永久 `pending` Job。
- [x] 新增“清理全部扫描任务”：取消活动 Job 后删除 Job/Item 历史，不删 IPA 资产/OpenList/`fa_category`。
- [x] IPA 资产新增单条删除与全部清空；同步清理解析/二进制/比对/分类绑定派生记录，不删除 OpenList 实际文件或 `fa_category`。
- [x] IPA 资产列表显示 OpenList 来源名称与 Source ID。
- [x] OpenList 默认隐藏已停用项，并提供“显示已停用/隐藏已停用”切换；停用不等于删除。
- [x] 新增 MySQL 5.7 幂等权限迁移 `2026092402_ipa_cleanup_controls.sql`。
- [x] IPA Online Update Release Gate Run `35945370518` — SUCCESS。
- [x] ZONOE Source Release Run `35945370658` — SUCCESS。
- [x] GitHub Release `source-v2026092402` 已发布。
- [x] Real GitHub Release online-update E2E (`2401 -> 2402`) — SUCCESS。
- [x] CI Artifact `zonoe-source-2026092402-online-update` 已生成。
- [ ] 真实 BaoTa 2401 -> 2402 在线更新后，点击“清理全部扫描任务”清掉 2401 遗留 pending，再执行全量扫描。
- [ ] 确认不再出现 open_basedir 错误，Job 自动进入 running/completed。
- [ ] 验证停用 OpenList 默认隐藏、资产来源展示与删除/清空按钮。

## 保留验证项

- [ ] active scan -> full scan 时旧 job cancelled、新 full job 正常完成。
- [ ] 暂停/继续不再显示 `think\exception\HttpResponseException`。
- [ ] 自动解析不再受旧 5 分钟/小时/每日配额阻断。
- [ ] 清空解析保留 IPA discovery/OpenList/source/`fa_category`。

## Next Task

在真实 BaoTa 服务器从 `source-v2026092401` 在线更新到 `source-v2026092402`。更新后先使用“清理全部扫描任务”删除 2401 故障期间留下的 pending Job，再点击一次全量扫描；预期不再触发 open_basedir，Worker 自动拉起并领取队列。若还有启动异常，检查 `runtime/log/ipa_scan_worker.log` 和 PHP 7.0 `disable_functions` 中的 `proc_open`。
