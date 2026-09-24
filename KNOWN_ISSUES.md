# Known Issues and Refactor Backlog

## P0 — 2026092404 real BaoTa runtime verification pending

- `source-v2026092404` 已正式发布；PHP 7.0、MySQL 5.7、HTTP load、Source Release、最终 Online Update Gate 和 `2403 -> 2404` 真实 GitHub Release 在线升级 E2E 均通过。
- 2404 已将 Web 扫描和自动解析统一为 PHP-FPM 响应结束后的同进程 queue consumer；不要求 Web 创建 PHP CLI 子进程，也不要求 CLI `ipa:worker` / `ipa:parse-worker` 常驻。
- Required checks: 真实服务器更新后验证一次完整扫描 + 自动解析；`discovered -> parsing -> parsed/parse_failed` 应无需 SSH/CLI Worker 完成。
- 同时验证手动/5 秒自动刷新、解析暂停/继续、解析失败重试，以及软件源保存/测试连接响应链。

## P1 — PHP-FPM worker occupancy during in-process scan/parse

- `fastcgi_finish_request()` 后浏览器已收到响应，但当前 FPM worker 会继续消费扫描或解析队列，因此在工作结束前仍被占用。
- 该设计消除了 BaoTa open_basedir、Web 子进程创建和 CLI secret/runtime 分裂问题，但大型 OpenList 树或大量 IPA 连续解析时的 FPM 容量影响需真实负载观察。
- 不应直接恢复 2401/2402 Web -> CLI launcher；未来如需完全独立执行器，应作为明确的部署级守护架构设计。

## P1 — Software-source validation semantics

- 软件源保存只做基本字段/端口/表名等合法性检查，不以数据库连接成功作为保存前置条件。这是当前明确语义，而不是缺陷。
- “测试连接”是判断 host/port/user/password/database/table 是否真实可用的独立操作；失败应显示 PDO/MySQL 原始错误。
- 2404 已修复成功响应被 `think\exception\HttpResponseException` broad catch 误报失败的问题；真实 BaoTa UI 仍待验证。

## P1 — Clearing active scan jobs is cooperative with an already-running consumer

- “清理全部扫描任务”先取消活动 Job/Item，再删除扫描队列历史。
- 已进入 OpenList 目录批次的 consumer 会在下一次 `assertJobActive()` 时停止；极小窗口内可能已有部分 asset upsert。
- cancelled full job 不执行最终 mark-missing reconciliation。

## P1 — Full-scan cancellation is cooperative during row consumption

- 扫描在 OpenList 调用前后及每 50 行检查取消状态。
- 若取消发生在批次中间，小部分行可能在下一次检查前完成同源 asset upsert。

## P1 — IPA asset cleanup production verification

- 单条/全部资产删除会清理 `ipa_parse_attempt`, `ipa_binary`, `ipa_compare_result`, `ipa_category_binding`，不修改 `fa_category`，也不删除 OpenList 实际 IPA 文件。
- 真实 BaoTa 数据尚需人工验证；OpenList 文件仍存在时，下次扫描应重新发现资产。

## P1 — Exact /license production Nginx interception

- `/authorization` 是在线更新安全的授权查询路由。
- ThinkPHP 仍保留 `/license`，但生产 Nginx 可能在到达 PHP 前由大小写不敏感的 LICENSE 安全规则截获。

## Stable announcement contract

- Public announcement authorization time is single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons.
- Internal authorization scopes remain independent for permission enforcement.
