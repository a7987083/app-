# Known Issues and Refactor Backlog

## P0 — 2026092402 real BaoTa runtime verification pending

- `source-v2026092402` 已正式发布；Online Update Gate、Source Release、PHP 7.0、MySQL 5.7、HTTP load gate 和 `2401 -> 2402` GitHub Release 在线升级 E2E 均通过。
- 2401 在真实 BaoTa 点击全量扫描时复现：FPM `open_basedir` 阻止 `is_file(/www/server/php/70/bin/php)`；请求已先创建 pending Job，因此后续增量扫描被活动任务互斥正确阻止。
- 2402 已移除站点目录外 PHP CLI 的文件系统探测，并把 Worker preflight 前移到 Job 插入之前。
- Required check: 真实服务器更新到 2402 后先“清理全部扫描任务”，再点全量扫描；不得再出现 open_basedir，任务应进入 running/completed。

## P1 — PHP-FPM may disable proc_open

- `IpaWorkerLauncher` 仍使用 `proc_open` 拉起 `PHP_BINDIR/php think ipa:worker`。
- 如果生产 PHP 7.0 的 `disable_functions` 禁止 `proc_open`，后台会明确报告该问题。
- 若真实服务器命中此限制，再采用宝塔/Supervisor/systemd 守护；不要放宽 open_basedir，也不要回退到 `/usr/bin/php`。

## P1 — Clearing active scan jobs is cooperative with an already-running worker

- “清理全部扫描任务”先将活动 Job/Item 标记 cancelled，再删除扫描队列历史。
- 已经进入一个 OpenList 目录批次的 Worker 会在下一次 `assertJobActive()` 时停止；与原 full-scan cooperative cancellation 一样，极小窗口内可能已有部分 asset upsert 完成。
- 清理扫描任务不会删除 IPA 资产，也不会执行 cancelled full job 的最终 mark-missing reconciliation。

## P1 — Full-scan cancellation is cooperative during row consumption

- 旧扫描在 OpenList 调用前后及每 50 行消费时检查取消状态。
- 若取消发生在一个批次中间，小部分行可能在下一次检查前完成同源 asset upsert。
- cancelled job 不会执行最终 full-scan mark-missing reconciliation。

## P1 — IPA asset cleanup production verification

- 单条/全部资产删除代码会清理 `ipa_parse_attempt`, `ipa_binary`, `ipa_compare_result`, `ipa_category_binding`，不修改 `fa_category`，也不删除 OpenList 实际 IPA 文件。
- 真实 BaoTa 数据上尚未人工验证；删除后若 OpenList 文件仍存在，下一次扫描应重新发现并建立资产记录。

## P1 — Remaining 2207 browser/runtime checks

- 暂停/继续不得显示 `think\exception\HttpResponseException`。
- `parse_enabled=1` 时不得再被旧 5 分钟/小时/每日配额阻断。
- 清空解析必须保留 IPA discovery、OpenList source、`fa_category`。

## P1 — Exact /license production Nginx interception

- `/authorization` 是在线更新安全的授权查询路由。
- ThinkPHP 仍保留 `/license`，但生产 Nginx 可能在到达 PHP 前由大小写不敏感的 LICENSE 安全规则截获。

## Stable announcement contract

- Public announcement authorization time is single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons.
- Internal authorization scopes remain independent for permission enforcement.
