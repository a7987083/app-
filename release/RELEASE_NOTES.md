# ZONOE 软件源 2026091708

## 紧急修复

- 修复 2026091707 新增的软件源 App 行缓存，在部分生产服务器 `runtime/cache` 不可写或文件缓存异常时可能把缓存错误升级为 ThinkPHP 异常页的问题。
- `SourceAppRepository` 现在改为严格 fail-open：缓存读取、写入、删除任一失败，都只记录日志并立即继续从 MySQL 读取 App 数据，不再影响 `/App/list` 或软件源域名可用性。
- 正常缓存命中逻辑保持不变；缓存正常时仍使用 15 秒共享缓存，缓存异常时只是退化为直接查数据库。
- 新增独立 PHP 7.0 fail-open 测试，覆盖缓存读取异常、缓存写入异常、删除异常和正常缓存命中。

## 兼容性

- 不改 `appstore` legacy RC4 协议。
- 不改 `appstore_v2` RSA/RC4/容器/Codec 协议。
- 不改客户端 JSON 字段、不要求客户端更新。
- 保留 2026091707 的 legacy bkey 缓存、HTTP gzip 和性能日志。
- 本版本无数据库结构变更，兼容 PHP 7.0 / MySQL 5.7。

## 在线更新

- 正式版本：`2026091708`
- 基线：`2026091707`
- GitHub Release：`source-v2026091708`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
- 可从 `2026091707` 通过后台 GitHub 在线更新直接升级到 `2026091708`。
