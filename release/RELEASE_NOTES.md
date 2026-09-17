# ZONOE 软件源 2026091801

## Phase 19.1 — V3 客户端安全同步契约

本版本以 `2026091714` 为稳定基线，继续保留旧 `/appstore` 和 Phase 18 V3 接口，并补齐客户端 SQLite/增量同步真正需要的服务端一致性边界。

## 更新内容

### 全量同步快照一致性

- `/appstore/v3/apps` 支持 `snapshot_revision`。
- 第一次全量分页建立快照 revision；后续页携带同一个 `snapshot_revision`。
- 如果同步期间软件源数据发生变化，服务端返回 `snapshot_valid=0`、`restart_required=1`，且不返回可能混合新旧 revision 的 apps。
- 客户端应丢弃未提交的临时全量同步结果，以新的 revision 从 `after_id=0` 重新开始。

### Delta 可续接窗口

- `/appstore/v3/meta` 新增 `min_delta_since`。
- `/appstore/v3/delta` 新增 `min_since`、`reset_required`、`reset_reason`。
- 本地 revision 早于服务端保留窗口时返回 `reset_reason=history_gap`，客户端必须重新全量同步。
- 本地 revision 高于服务端当前 revision 时返回 `reset_reason=future_revision`，避免错误游标永久卡死。
- 正常 delta 继续使用 `next_since`、`has_more`、`upserts`、`deleted`，旧 V3 字段保持兼容。

### 兼容性

- `/appstore` 路由和 payload 不变。
- `source_v3=0` 时仍以 `supported=0` + `fallback=appstore` 明确回退。
- 黑名单、授权失败等业务拒绝仍为 `supported=1`，不能通过 fallback 绕过。
- 普通 / V2 加密选择和 envelope 不变。
- PHP 7.0、MySQL 5.7 保持兼容。

### 验证

- Phase 19.1 feature CI Run `35261910066` 已通过。
- PHP 7.0：Phase18 V3 兼容、Phase19 snapshot/reset 合约、在线更新 ZIP 内容检查通过。
- MySQL 5.7：revision window、retention boundary、history gap、future revision 测试通过。
- 正式发布流水线继续执行完整 PHP 7.0 回归、MySQL 5.7 迁移、ZIP/SHA256 生成，并执行 `2026091714 -> 2026091801` GitHub Release 在线更新 E2E。

## 在线更新

- 正式版本：`2026091801`
- 基线：`2026091714`
- GitHub Release：`source-v2026091801`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
