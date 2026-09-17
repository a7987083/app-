# ZONOE 软件源 2026091713

## 更新内容

### Phase 18.2 — 软件源加密模式互斥选择

- 系统配置中的“软件源加密”由旧的开/关改为三选一：`关闭 / 普通 / V2`，普通与 V2 不能同时开启。
- 继续复用原 `fa_config.opencry`，不新增并行开关：`0=关闭`、`1=普通(appstore)`、`2=V2(appstore_v2)`。
- 旧服务器升级时原 `opencry=0/1` 原样保留；迁移可重复执行，`opencry=2` 也不会被后续在线更新覆盖。
- 后台选择的加密模式成为服务端权威值：选择“普通”时客户端即使请求 V2 也按普通协议输出；选择“V2”时客户端即使未声明 V2 也按 V2 输出。
- `opencry=2` 对旧布尔判断仍表现为“加密已开启”，因此复用现有 `App::emitPayload()`、加密 provider、gzip 和 SourcePerf，不复制第二套发送链。
- `/appstore` 与 `/appstore/v3/meta`、`/appstore/v3/apps`、`/appstore/v3/delta` 统一通过同一协议选择策略，避免旧源和 V3 使用不同加密模式。
- 保留 2026091712 的 V3 revision、游标分页、delta 增量同步、change-log fail-open；保留 2026091711 的 bkey 缓存、App 缓存、cache fail-open、gzip 和性能日志；保留 2026091710 的 `renewal_entry` schema-adaptive 修复。

## 兼容性

- 未修改普通 `appstore` 和 `appstore_v2` 的密码算法、密文 envelope 或客户端解密格式。
- 关闭加密时继续返回原明文 JSON。
- 现有数据库无需手工改字段，只更新 `fa_config.opencry` 这一行的展示类型和可选值。
- 实际软件源浏览客户端的 SQLite/V3 本地同步代码不在本服务端仓库中，本版本不向无关的 dylib/菜单工程混入客户端代码；1712 V3 服务端接口保持可用，待对应客户端源码接入后继续 Phase 18.2 客户端部分。

## 验证要求

- PHP 7.0：加密模式类、配置兼容层、AppStorePayload 语法和互斥协议契约必须通过。
- MySQL 5.7：1713 配置迁移必须可重复执行；旧值 1、新值 2、异常值回退和缺失行初始化均验证。
- 在线更新 ZIP 必须包含 `SourceEncryptionMode.php`、`SourceConfigRepository.php`、`AppStorePayload.php` 和 `2026091713_source_encryption_mode.sql`。
- 正式发布继续执行全量 PHP 7.0 回归、MySQL 5.7 迁移链、ZIP/SHA256、GitHub Release 和 2026091712 → 2026091713 真实在线更新 E2E。

## 在线更新

- 正式版本：`2026091713`
- 基线：`2026091712`
- GitHub Release：`source-v2026091713`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
