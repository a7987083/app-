# ZONOE 软件源 2026092436

## 更新内容

## 长期运行稳定性增强

### 数据库 Retention

- 修复 `ipa:maintenance` 仍访问已删除 `fa_dylib_nonce` 导致后续清理中断的问题。
- `fa_dylib_auth_challenge` 保留 1 天。
- `fa_dylib_device_session` 在过期后保留 1 天。
- `fa_dylib_verify_log` 默认保留 30 天。
- `fa_api_request_log` 默认保留 30 天。
- Device Key 不限数量，365 天未使用才自动删除。
- IPA Parse Attempt 默认保留 30 天。
- 终态 Scan Item 保留 7 天；终态 Scan Job 保留 30 天。
- 授权事件、换绑日志、管理员日志、软件源变更日志默认保留 365 天。
- missing IPA 超过 365 天且没有人工分类绑定时才自动删除，同时清理对应派生数据。

### 删除安全

- 高频大表统一使用有界批量删除：每批最多 5,000 行、每次 maintenance 最多 20 批。
- Scan 历史清理加入游标，避免前面的空任务阻断后面的历史记录。
- missing IPA 的主资产删除在事务中同步清理 binary、app identity、compare result、parse attempt。
- 有人工 category binding 的 IPA 永不自动删除。

### 磁盘 Retention

- runtime 日志默认保留 30 天。
- 每日 maintenance 自动复用 UpdateOps 现有清理策略：
  - update status：14 天
  - update history：90 天
  - rollback backup：30 天
- maintenance systemd service/timer 已加入在线更新包。

### MySQL 5.7

- 新增 `2026092436_retention_indexes.sql`，为长期清理增加必要索引。
- 专项 CI 已验证 MySQL 5.7 连续执行两次迁移保持幂等。

## 兼容性

- 基于正式 `source-v2026092435` / `bda2656a699e93162ea38514f21e5027fc92ece9`。
- 不改变 2435 的 `UDID + Dylib Key + PublicKey` 多 Key 模型。
- 不改变 Protocol v3 canonical 签名字段。
- 不改变正常授权判定逻辑。

## 升级路径

`source-v2026092435 -> source-v2026092436`
