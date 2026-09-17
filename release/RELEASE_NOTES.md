# ZONOE 软件源 2026091714

## Phase 18.3 — V3 开关与明确回退协议

### 更新内容

- 系统配置新增 `V3软件源` 开关，固定显示在 `软件源加密` 正下方。
- 配置键为 `source_v3`：`1=开启`、`0=关闭`；升级默认开启，保持 2026091712/1713 已启用 V3 的行为。
- `/appstore` 永久保留并保持原有兼容行为；V3 仍为附加接口：`/appstore/v3/meta`、`/appstore/v3/apps`、`/appstore/v3/delta`。
- V3 开启时健康接口返回 `supported=1`；客户端可优先探测 `/appstore/v3/meta` 并继续使用分页/增量同步。
- V3 关闭时 V3 接口明确返回 `code=0`、`supported=0`、`fallback=appstore`，让支持新协议的客户端无歧义回退到原 `/appstore`。
- 黑名单、授权失败等业务拒绝仍属于 `supported=1` 的 V3 服务，不能被客户端当作“不支持 V3”而回退绕过。
- V3 增量表不可用时继续给出可诊断错误；服务端能力与业务错误分开表达。
- V3 开关保存后通过现有 ConfigModel 写入事件清理 SourceConfigRepository 缓存，配置立即生效。
- 软件源加密仍沿用 2026091713 的互斥模式：关闭 / 普通 / V2，V3 与旧 `/appstore` 共用同一加密响应策略。

### 地址兼容

用户和客户端仍只需要保存基础软件源地址：

`https://app3.zonoeios.xyz/appstore`

支持 V3 的客户端可从该地址派生 `/v3/meta`、`/v3/apps`、`/v3/delta`；不支持 V3 的旧客户端继续直接访问 `/appstore`，无需重新添加软件源。

### 数据库

新增幂等迁移：`2026091714_source_v3_toggle.sql`。

- 已存在合法 `0/1` 值时原样保留。
- 配置不存在时创建并默认开启。
- 异常历史值修复为开启，避免升级后意外关闭已在生产使用的 V3。
- 兼容 MySQL 5.7。

### 兼容性与边界

- PHP 7.0 保持兼容。
- MySQL 5.7 保持兼容。
- 不改变旧 `/appstore` 的 payload、授权逻辑或 URL。
- 不改变普通 / V2 加密算法和 envelope。
- 本版本完成服务端 V3 能力开关、能力声明和 fallback 协议；实际软件源浏览客户端的 SQLite/V3 自动同步将在对应客户端源码接入后进入下一阶段，不向无关的 dylib/菜单工程混入客户端代码。

### 验证要求

- Phase 18.3 PHP 7.0 契约：V3 开关、`supported`、fallback、旧路由、后台显示顺序。
- Phase 18.3 MySQL 5.7：迁移重复执行、默认开启、关闭值保持。
- 正式 PHP 7.0 全回归。
- 正式 MySQL 5.7 全迁移链。
- 在线更新 ZIP 必须包含 `SourceV3.php`、后台 `Config.php` 和 `2026091714_source_v3_toggle.sql`。
- 2026091713 → 2026091714 真实 GitHub Release 在线更新 E2E。

## 在线更新

- 正式版本：`2026091714`
- 基线：`2026091713`
- GitHub Release：`source-v2026091714`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
