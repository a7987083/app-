# Known Issues and Refactor Backlog

## P0 — 2026092401 real BaoTa runtime verification pending

- `source-v2026092401` 已正式发布；Online Update Gate、Source Release、PHP 7.0、MySQL 5.7 和 `2207 -> 2401` GitHub Release 在线升级 E2E 均通过。
- 真实服务器曾确认：站点 PHP-FPM 是 PHP 7.0，而 SSH 默认 `/usr/bin/php` 指向 PHP 8.2；CLI 手工运行还缺失 FPM 的 `PHP_IPA_SERVER_SECRET`。
- 2026092401 已针对这条生产运行链修复，但尚未在用户实际 BaoTa 服务器安装后验证。
- Required check: 在线更新到 2026092401 后，点击一次全量扫描，任务应自动从 `pending` 被 Worker 领取并进入 `running/completed`，无需 SSH 手工启动 Worker。

## P1 — PHP-FPM may disable proc_open

- `IpaWorkerLauncher` 使用 `proc_open` 从当前 FPM 环境拉起 `PHP_BINDIR/php think ipa:worker`。
- 如果生产 PHP 7.0 的 `disable_functions` 禁止 `proc_open`，后台会明确报“服务器已禁用 proc_open，无法自动启动 IPA 扫描 Worker”。
- 若真实服务器命中此限制，再选择现有宝塔/Supervisor/systemd 守护方案；不要回退到 `/usr/bin/php`。

## P1 — Full-scan cancellation is cooperative during row consumption

- 旧扫描在 OpenList 调用前后及每 50 行消费时检查取消状态。
- 若取消发生在一个批次中间，小部分行可能在下一次检查前完成同源 asset upsert。
- cancelled job 不会执行最终 full-scan mark-missing reconciliation。

## P1 — Remaining 2207 browser/runtime checks

- 暂停/继续不得显示 `think\exception\HttpResponseException`。
- `parse_enabled=1` 时不得再被旧 5 分钟/小时/每日配额阻断。
- 清空解析必须保留 IPA discovery、OpenList source、`fa_category`。
- PR #22/#23 保持 draft，除非用户明确要求合并。

## P1 — Exact /license production Nginx interception

- `/authorization` 是在线更新安全的授权查询路由。
- ThinkPHP 仍保留 `/license`，但生产 Nginx 可能在到达 PHP 前由大小写不敏感的 LICENSE 安全规则截获。

## Stable announcement contract

- Public announcement authorization time is single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons.
- Internal authorization scopes remain independent for permission enforcement.
