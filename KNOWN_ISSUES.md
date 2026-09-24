# Known Issues and Refactor Backlog

## P0 — 2026092403 real BaoTa runtime verification pending

- `source-v2026092403` 已正式发布；PHP 7.0、MySQL 5.7、HTTP load gate、Source Release、最终 Online Update Gate 和 `2402 -> 2403` GitHub Release 在线升级 E2E 均通过。
- 2401/2402 在真实 BaoTa 上暴露了同一架构问题的两个阶段：先是站点 `open_basedir` 阻止外部 PHP CLI 路径探测，随后是 Web 请求无法创建扫描子进程。
- 2403 已把 UI 扫描执行改为当前 PHP-FPM 进程在响应结束后继续消费现有数据库队列；不再要求 Web 请求创建 CLI Worker。
- Required check: 真实服务器更新到 2403 后先“清理全部扫描任务”，再点全量扫描；任务应无需 SSH/CLI Worker 即进入 `running/completed`。

## P1 — PHP-FPM worker occupancy during in-process scan

- 2403 在 `fastcgi_finish_request()` 后由当前 FPM worker 继续消费扫描队列，因此浏览器可先收到响应，但该 FPM worker 在扫描完成前仍被占用。
- 当前设计避免了生产环境的子进程/open_basedir/CLI secret 分裂问题，但大型 OpenList 树在高并发站点上的 FPM 容量影响仍需真实负载观察。
- 不应为了规避该风险直接恢复 2401/2402 的 Web -> CLI launcher；若未来需要完全独立执行器，应作为明确的部署级守护架构设计，而不是由 Web 请求临时拉起。

## P1 — Clearing active scan jobs is cooperative with an already-running consumer

- “清理全部扫描任务”先将活动 Job/Item 标记 cancelled，再删除扫描队列历史。
- 已经进入一个 OpenList 目录批次的 consumer 会在下一次 `assertJobActive()` 时停止；极小窗口内可能已有部分 asset upsert 完成。
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
