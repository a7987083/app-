# ZONOE 软件源 2026092433

## 更新内容

### Dylib 首次设备公钥登记改为 active UDID proof

- `/index/index/apiface` 在确认 UDID 已激活且未过期后，保留原 `expire / ts / nonce / sign / authorizations` 字段，并追加短时 `auth_proof` 与 `auth_proof_expires_at`。
- `auth_proof` 为服务器 HMAC 签发的短时证明，不包含卡密，也不能由客户端自行生成。
- `/index/dylib_verify/challenge` 对首次 Device Key enrollment 强制校验 `auth_proof`，并将 `challenge + auth_proof + UDID + Dylib + public_key_hash` 绑定到一次性 Challenge 上。
- `/index/dylib_verify/verify` 首次 enrollment 不再读取或要求 `license_code`；设备 P-256 签名 canonical 中加入 `auth_proof`，形成 active UDID proof + one-time challenge + device private key 三层绑定。
- 首次登记前再次检查 UDID 当前仍有 `jh=1` 且 `endtime > now` 的有效授权，避免使用过期 proof 完成 enrollment。

### 旧客户端兼容

- 已经存在 active Device Key 的旧 v3 客户端仍可继续使用原 Challenge / canonical 流程，不强制 `auth_proof`。
- 只有“首次登记新 Device Key”必须使用新的 active-UDID proof 流程；不再回退到旧 `license_code` enrollment。
- 新版 iOS `ZONVerifyClient` 会自动根据 Verify Endpoint 请求 `/index/index/apiface?udid=...` 获取 proof，然后再执行 Challenge / Verify，不需要客户端保存已激活卡密。

### 后台测试入口收敛

- 删除重复的 `dylib_api_test/index` 独立测试页及其 Controller / View / JS。
- API 调试继续统一使用现有“系统配置 → API 接口测试”，避免维护两套测试入口。

### 验证记录

- 保留 2433 已加入的 `/index/dylib_verify/verify` `bad_request` 审计。
- 验证记录继续支持 Dylib / code / BundleID / 版本 / UDID / IP / 时间筛选。
- 2431 的 `fa_dylib_verify_log.udid/ip` migration 仍为生产库必需结构。

### 协议文档

- Verify Request Contract 移除 `license_code` enrollment 字段，新增必需的 `auth_proof`。
- canonical v3.1 顺序加入 `auth_proof`。
- 新增 `auth_proof_missing / invalid / expired / mismatch / authorization_inactive` 结果码说明。

### 兼容性

- 基于正式 `source-v2026092432` 开发。
- 保留 2432 `/unbind` UDID 1-128 字符支持。
- 保留 2431 验证记录原始 UDID/IP 与版本 offline_grace 继承。
- 保留 2430 Secretless Auth、RSA Runtime Config、P-256 Device Key 与一次性 Challenge。

## CI / 验证

`Dylib Auth Proof 2433 CI` 覆盖：

- PHP 7.0 syntax；
- `/apiface` proof 签发接线；
- Challenge/Verify proof 绑定；
- 首次 enrollment 不再调用 `AuthorizationLicense::query()` / `license_code`；
- iOS 客户端自动获取 `/apiface` auth_proof；
- canonical 双端包含 `auth_proof`；
- 重复 `dylib_api_test` 三件套已删除；
- 在线更新 manifest 包含新的 auth-proof Service、服务端与 iOS 客户端改动。

正式发布仍保持 `AUTO_RELEASE=0`，直到最终候选完整 regression / source integrity / MySQL / HTTP / online-update E2E 再次通过后再开启发布。

## 升级路径

`source-v2026092432 -> source-v2026092433`
