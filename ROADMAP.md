# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026091801-phase19-client-sync`
- Phase: `19.1`
- Version: `2026091801`
- Release commit: `6e3c7a3af25843c3f329207bd6b4c4e06e182e57`
- Release: `source-v2026091801`
- Phase19 feature CI: `35261910066` — SUCCESS
- Release CI: `35262273073` — SUCCESS
- Online update E2E: `2026091714 -> 2026091801` — SUCCESS
- `main` 不是活动开发/发布基线。

## Phase 19 — V3 客户端安全同步

### 19.1 服务端同步契约 — 已完成并发布

- `/appstore/v3/apps` 支持 `snapshot_revision`，全量分页绑定单一 revision。
- 同步期间 revision 变化时返回 `snapshot_valid=0`、`restart_required=1`，不向客户端返回混合快照。
- `/appstore/v3/meta` 暴露 `min_delta_since`。
- `/appstore/v3/delta` 暴露 `min_since/reset_required/reset_reason`。
- 变更历史缺口返回 `history_gap`；客户端 revision 高于服务端返回 `future_revision`，两者均要求重新全量同步。
- 旧 `/appstore`、V3 开关、授权/黑名单语义、普通/V2 加密协议保持兼容。
- PHP 7.0 / MySQL 5.7 专项测试及正式 Release 全回归通过。
- 在线更新 manifest 已包含 `SourceV3.php`、`SourceSyncV3.php`、`SourceChangeLog.php`。
- 正式 GitHub Release 在线更新 E2E 已验证 `2026091714 -> 2026091801`。

### 19.2 iOS 客户端 SQLite 同步 — Next Task

当前 `app-` 仓库是 ThinkPHP/FastAdmin 服务端仓库。仓库代码检索未发现 Objective-C 或 `sqlite3` 客户端实现，因此不能在本仓库内伪造“客户端 SQLite 已接入”。下一阶段必须在对应的软件源浏览客户端源码仓库完成：

1. 基础地址自动探测 `/v3/meta`，仅 `supported=0` 时回退旧 `/appstore`。
2. SQLite 建立 source/app/sync-state 表，使用 transaction 保证完整提交。
3. 首次同步使用 `/v3/apps` 分页，并固定 `snapshot_revision`；`restart_required=1` 时回滚临时事务并重启。
4. 后续使用 `/v3/delta` 应用 upsert/delete；`reset_required=1` 时清除临时游标并重新全量同步。
5. 本地 revision 只在事务成功后推进，禁止“revision 已更新但 apps 未完整落盘”。
6. 覆盖明文/普通/V2 三种响应模式，以及 V3 开关关闭、授权拒绝、黑名单、断网、进程中断等恢复场景。
7. 完成 iOS 真机 E2E 后再标记客户端部分完成。

## Phase 20 — V3 Production Hardening

在客户端接入完成后推进：

- `fa_source_change` retention/GC 与 `min_delta_since` 联动。
- delta/history 容量、索引和查询成本监控。
- ETag/条件请求、gzip、分页大小与请求合并优化。
- 同步耗时、失败原因、全量重置原因可观测性。
- 大规模 Category/变更日志压测。
- 生产 BaoTa/MySQL/browser/iOS 真机回归闭环。

## 保持不变的稳定边界

- 不改变旧 `appstore / appstore_v2` 公共字段、加密 envelope 和 URL。
- V3 不支持与业务拒绝必须严格区分：只有 `supported=0` 才能 fallback。
- day/week/month/quarter/year 仍为 1/7/30/90/360 天。
- 卡密仍一次性消费，授权时长可叠加；`transfer_count` 仍表示剩余换绑次数。
- BaoTa 部署契约和 `BT_DB_*` 占位符保持。
- `App-mb.php` / `Index2.php` 不得恢复。
- ThinkPHP 5.0.24 / FastAdmin 升级继续延期，直到集成测试覆盖足够。

其他长期问题见 `KNOWN_ISSUES.md`。
