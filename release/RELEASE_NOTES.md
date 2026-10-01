# ZONOE 软件源 2026092431

## 更新内容

### Dylib 验证记录可直接查看 UDID / IP

- 验证日志新增原始 `UDID` 与客户端 `IP` 字段，并保留原有 `udid_hash / ip_hash` 供内部关联使用。
- 后台验证记录列表和筛选改为直接按 UDID / IP 使用，不再把 UDID Hash 作为主要排障字段。
- 历史记录无法反推出原始 UDID/IP，因此旧记录保持为空；升级后的新验证会写入完整原值。

### 版本离线时间改为“继承 Dylib 默认”

- `Dylib 基本信息 -> 默认离线可用` 继续作为全局默认值。
- `dylib_version.offline_grace` 允许为 `NULL`；`NULL` 表示继承 Dylib 默认值。
- 新增版本默认不再强写 `900` 秒。
- 编辑已有版本时可以选择继承默认值，或显式填写自定义秒数；`0` 仍表示明确禁止离线。
- 现有数据库里已经保存的 900/其他数值不会被迁移自动改写，避免误伤历史手工配置。

### 数据库迁移

新增 `2026092431_dylib_log_identity_offline_inherit.sql`：

- `fa_dylib_verify_log` 增加 `udid`、`ip`；
- 增加 UDID/IP 索引；
- `fa_dylib_version.offline_grace` 改为 nullable；
- MySQL 5.7 下可重复执行；
- 不改写已有版本的离线值。

### 兼容性

- 继续保留 2026092430 的 Secretless Auth v3、Challenge、Device Key、RSA Runtime Config 签名和原有 API。
- 原 2428/2430 Dylib 管理、版本、通知、Codegen、API 文档、App Identity、权限、App Update、session token 等功能保持不变。

## CI / 验证

`Dylib Admin 2431 CI` 已覆盖并通过第一轮技术门禁：

- PHP 7.0 / JavaScript syntax；
- 验证记录 UDID/IP 合约；
- offline_grace 继承合约；
- 2430 Secretless 与旧 API 表面保留断言；
- MySQL 5.7 migration 双次执行；
- 历史 offline_grace 值保持不变；
- 在线更新 ZIP 构建并确认 2431 migration 入包。

正式发布仍需 canonical Release gate 全绿，包括完整 regression、source integrity、MySQL、HTTP gate、真实 GitHub Release 在线升级 E2E。

## 升级路径

`source-v2026092430 -> source-v2026092431`
