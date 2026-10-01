# ZONOE 软件源 2026092433

## 更新内容

### Dylib Protocol v3 API 测试器

- 新增后台 `dylib_api_test/index` 测试页，专门测试当前 Secretless Protocol v3，不再把旧 `/index/index/dylib`、`/index/index/apiface` 当作新验证链测试。
- Config 测试调用真实 `DylibRuntimeConfigService::bootstrap()`。
- Challenge 测试调用真实 `DylibDeviceAuthService::issueChallenge()`。
- 可生成当前 Verify 请求对应的 canonical 签名原文，方便核对 P-256 ECDSA-SHA256 签名。
- Verify 测试调用真实 `DylibVerificationService::verify()`，显示 endpoint、耗时和完整响应。
- Challenge 成功后自动把 `challenge_id`、`challenge`、UDID、Dylib Key、设备公钥合并到 Verify JSON；设备签名仍必须由对应真实 P-256 私钥生成。
- 测试器不会生成并登记虚假的设备私钥，也不会绕过首次设备公钥登记所需的有效卡密授权。

### 验证记录补全

- `/index/dylib_verify/verify` 缺少必填字段返回 `bad_request` 时，现在也会写入 `fa_dylib_verify_log`。
- 记录可继续按 Dylib / code / BundleID / 版本 / UDID / IP / 时间筛选。
- Config 与 Challenge 仍不写验证结果日志，因为它们不是最终 Verify 结果。

### 兼容性

- 基于正式 `source-v2026092432` 开发。
- 保留 2432 `/unbind` UDID 1-128 字符支持。
- 保留 2431 验证记录原始 UDID/IP 与版本 offline_grace 继承。
- 保留 2430 Secretless Auth v3、RSA Runtime Config、P-256 Device Key 与一次性 Challenge。

## CI / 验证

`Dylib API Tester 2433 CI` 覆盖 PHP 7.0 syntax、前端 JS syntax、Config/Challenge/Canonical/Verify 真实 Service 调用契约、bad_request 审计接线及在线更新 manifest 完整性。

正式发布继续经过 canonical Release gate：完整 PHP regression、source integrity、真实 MySQL 5.7 migration、HTTP 并发 gate 和真实 GitHub Release 在线升级 E2E。

## 升级路径

`source-v2026092432 -> source-v2026092433`
