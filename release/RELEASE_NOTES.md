# ZONOE 软件源 2026091707

## 更新内容

- 优化 `appstore` / `appstore_v2` 大软件源的服务器响应链路，客户端 JSON 字段、密文协议和现有解密逻辑保持兼容。
- `appstore` legacy bkey 新增服务器本地持久缓存：默认 15 分钟内直接使用 `runtime/source_crypto/legacy_bkey.json`，不再为每个客户请求重复访问 `update.json` / `key.json`。
- legacy bkey 刷新失败时可在默认 24 小时窗口内使用最近一次有效缓存，避免外部 key 服务波动拖慢或阻断添加软件源；缓存文件位于非 public 目录并尝试设置为 0600。
- 软件源 App 公共行新增 15 秒共享缓存，客户集中添加/刷新时减少重复扫描 `fa_category`；新增/删除 App 会主动清缓存，编辑场景最长仅保留该短 TTL。
- 新增软件源性能日志：记录 App 数、App 数据来源、JSON 构造、加密耗时、总耗时、JSON/响应大小、峰值内存、实际加密 provider，以及 legacy key 来自缓存还是远端。
- 大响应新增标准 HTTP gzip：仅客户端声明 `Accept-Encoding: gzip` 时启用，应用层 `appstore/appstore_v2` 字符串在 HTTP 解压后保持字节一致；可用 `SOURCE_HTTP_GZIP=0` 随时关闭。
- gzip 默认只处理大于 1 KB 的响应，并使用 level 1 降低 CPU 开销；若 PHP 已启用 `zlib.output_compression` 则不重复压缩。

## 性能基准（PHP 7.0 CI，模拟数据）

基准用于确认数量级，不代表具体生产服务器或客户设备耗时；以下仅为服务器端加密阶段：

- 1,000 App / 0.42 MB JSON：`appstore` 约 77 ms，`appstore_v2` 约 164 ms。
- 5,000 App / 2.12 MB JSON：`appstore` 约 376 ms，`appstore_v2` 约 812 ms。
- 10,000 App / 4.23 MB JSON：`appstore` 约 761 ms，`appstore_v2` 约 1.64 s。
- 20,000 App / 8.47 MB JSON：`appstore` 约 1.52 s，`appstore_v2` 约 3.30 s。
- 50,000 App / 21.17 MB JSON：`appstore` 约 3.84 s，`appstore_v2` 约 8.21 s。

HTTP gzip level 1 在同一 10,000 App 基准中：

- `appstore` 加密文本约 5.65 MB → 4.37 MB，gzip 约 148 ms。
- `appstore_v2` 加密文本约 5.71 MB → 4.38 MB，gzip 约 148 ms。
- 50,000 App 时约 28.2–28.5 MB → 21.8–21.9 MB，传输量减少约 22–23%。

预分配字符串形式的 RC4/Codec 候选实现虽然可快约 5–7%，但显著增加峰值内存，因此未进入生产代码。

## 可选环境参数

- `SOURCE_ENCRYPTION_PROVIDER=local`：强制本地加密，避免本地失败后把大 payload 转发到外部 provider；未设置时继续保持既有 `local_fallback` 兼容策略。
- `SOURCE_LEGACY_BKEY_TTL`：legacy bkey 新鲜缓存秒数，默认 900。
- `SOURCE_LEGACY_BKEY_STALE_TTL`：远端刷新失败时允许旧 bkey 的最长秒数，默认 86400。
- `SOURCE_LEGACY_BKEY_CACHE_FILE`：自定义 bkey 缓存路径。
- `SOURCE_PERF_LOG=1`：记录所有软件源性能日志；未开启时仍会自动记录超过默认 500 ms 的慢请求。
- `SOURCE_PERF_SLOW_MS`：慢请求阈值，默认 500 ms。
- `SOURCE_HTTP_GZIP=0`：关闭代码层 HTTP gzip。
- `SOURCE_HTTP_GZIP_MIN_BYTES`：gzip 最小响应字节数，默认 1024。

## 兼容性

- 不改变 `appstore` legacy RC4 密文向量。
- 不改变 `appstore_v2` 的 RSA/RC4/`0xFEEDFACF` 容器和自定义 Codec。
- 不改变全软件源、仅验证、指定 App 三种卡密语义、续费入口、换绑、授权签名或现有客户端接口。
- 本版本无数据库结构变更，兼容 PHP 7.0 / MySQL 5.7。

## 验证

- 原有 `source_encryption_provider_test` 加密向量通过。
- 加密 provider 策略测试通过。
- legacy bkey 缓存与直接使用同一 key 的密文逐字节一致。
- HTTP gzip 解压后应用层响应逐字节一致，并验证 `gzip;q=0` / 环境关闭行为。
- 1k / 5k / 10k / 20k / 50k 双协议基准全部通过。
- 正式发布继续执行 PHP 7.0 全回归、MySQL 5.7 迁移、ZIP/SHA256、GitHub Release 和真实在线升级 E2E。

## 在线更新

- 正式版本：`2026091707`
- GitHub Release：`source-v2026091707`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
- 可从 `2026091706` 通过后台 GitHub 在线更新直接升级到 `2026091707`。
