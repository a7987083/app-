# ZONOE 软件源 2026091906

## 更新内容

本版本基于 `2026091905`，针对 `/appstore` 大响应在 PHP 7.0 / 128M 内存限制下出现间歇性 HTTP 500 的问题做性能收口，重点降低大对象生命周期与加密/传输阶段的峰值内存，不改变既有授权、缓存隔离和 appstore/appstore_v2 协议语义。

### 1. App 响应大对象提前释放

- `application/index/controller/App.php` 在 JSON 构建完成后不再继续持有完整 `$payload['apps']` 大数组。
- `app_count` 在释放前提前提取，`SourcePerformance` 日志不再为了统计数量保留整个 payload。
- 加密完成后继续提前释放 JSON、加密结果包装和密文临时引用，减少响应 envelope 生成阶段的并存大字符串。

### 2. 大响应 PHP gzip 内存保护

- `application/common/library/SourceResponse.php` 为 PHP 一次性 `gzencode()` 增加大响应保护。
- 默认 `SOURCE_HTTP_GZIP_MAX_BYTES=8388608`（8 MiB）；超过阈值时跳过 PHP 层 one-shot gzip，直接保持应用层响应字节不变。
- 仍保留 `SOURCE_HTTP_GZIP` / `SOURCE_HTTP_GZIP_MIN_BYTES` 以及新增上限配置，未删除原 gzip 功能。
- `encryptedBody()` 在不存在 `@@@` marker 时避免无意义的大字符串替换扫描，同时保留原 marker 兼容行为。

### 3. appstore_v2 去除完整 container 副本

- `application/common/library/SourceEncryptionProvider.php` 保留原 V2 container 字节布局与 variable-width codec 规则。
- 编码阶段改为 header + RC4 payload 分段送入 codec，避免再构造一份与 payload 同量级的完整 `$container` 字符串。
- codec 的 bit buffer / bit count 跨分段连续保留，输出语义与原实现一致。

### 4. 回归与兼容性

- 不修改 RC4 key 规则、RSA PKCS#1 v1.5、V2 magic、V2 alphabet、JSON 外层 key、授权判定、downloadURL 裁剪、缓存 entitlement 隔离及 Nuosike fallback 语义。
- 已补 V2 multipart codec 边界等价测试，覆盖 1/5/6/7/31/32/63/64/65/127/128/129/255/256 等分割位置及多段组合。
- 已补 App payload 提前释放与大响应 gzip guard contract。
- Phase 19.2 Legacy AppStore Performance PHP 7.0 lint、regression contracts、online update package verify 已通过当前候选。

### 发布与在线更新

- 版本由 `2026091905` 升级为 `2026091906`，用于让现有在线更新面板识别为新版本。
- 在线更新包继续由既有 `release/online-update-files.txt` + `tools/build_online_update.php` 生成，不修改 `ZONOE Source Release` workflow 语义。
- `App.php`、`SourceEncryptionProvider.php`、`SourceResponse.php` 均已在现有在线更新 manifest 中。
- 本次运行代码修改未涉及 `UpdateIntegrity::files()` 中的四个签名文件，因此 `file_sign` 继续使用 `8be29c04c34ba1d1cc7ec77d392b2bee`；正式 Release workflow 仍会重新计算并校验。

### 发布后验证

正式在线更新完成后，继续验证真实 9 MiB 级 `/appstore`：

- 连续请求 30～50 次确认 HTTP 500 / OOM 是否归零。
- 对比 `memory_peak_mb`、`encryption_ms`、`total_ms`、`response_bytes`。
- 检查 PHP error log 是否仍出现 `Allowed memory size exhausted`。
