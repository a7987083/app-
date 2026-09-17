# ZONOE 软件源 2026091712

## 更新内容

### Phase 18.1 — 软件源 V3 万级同步基础

- 旧 `/appstore` 路由和 `appstore / appstore_v2` 协议保持不变，现有客户端无需更新。
- 新增 `/appstore/v3/meta`：返回 V3 协议版本、当前 source revision、正常 App 总数、分页/增量上限和增量表可用状态。
- 新增 `/appstore/v3/apps`：使用 `after_id + limit` 做稳定游标分页；默认 200、最大 500。每个 App 额外包含稳定 `id` 和 `weigh`，供新客户端本地数据库和排序使用。
- 新增 `/appstore/v3/delta`：使用 `since + limit` 按 revision 增量拉取，仅返回 `upserts` 和 `deleted`；默认 200、最大 1000，并返回 `next_since/current_revision/has_more`。
- V3 继续复用现有卡密授权、指定 App 授权、黑名单和 `appstore/appstore_v2` 加密封装；不会绕过原授权规则。
- 新增 `fa_source_change`：`revision` 使用 MySQL AUTO_INCREMENT，保证并发写入下仍有唯一、单调递增的增量游标。
- App 新增、模型更新、删除、后台直接编辑、批量状态更新和拖动排序均写入 change log。
- change log 写入采用 fail-open：增量表异常只记录日志，不得影响旧 `/appstore`、后台 App 管理或 1711 的稳定功能。
- 首次全量同步建议：先读取 `/appstore/v3/meta` 记住 revision，再按 `after_id` 拉完整页，最后调用 `/appstore/v3/delta?since=<初始revision>` 补齐同步期间发生的变化。

## 与 2026091711 的关系

- 完整保留 1710 的 `renewal_entry` schema-adaptive 修复。
- 完整保留 1711 恢复的 legacy bkey 缓存、15 秒 App 缓存、cache fail-open、HTTP gzip、SourcePerf 和 50k benchmark。
- 保持续费入口、三种卡密用途、授权叠加、`apiface` 签名、换绑额度、卡密/App 删除映射清理和授权总览刷新。
- 本版本只是新增 V3 服务端基础，不会自动把现有客户端切换到 V3。

## 验证

- Phase 18.1 PHP 7.0 契约测试：旧 `/appstore` 路由保持、V3 三接口、分页/增量字段、App 变更 revision、拖动排序 revision、change-log fail-open。
- MySQL 5.7：`fa_source_change` 迁移可重复执行；revision 自增且 delta cursor 顺序正确。
- 正式发布继续要求既有 PHP 7.0 全回归、MySQL 5.7 迁移链、在线更新 ZIP/SHA256 和真实 GitHub Release E2E 全部通过。

## 在线更新

- 正式版本：`2026091712`
- 基线：`2026091711`
- GitHub Release：`source-v2026091712`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
