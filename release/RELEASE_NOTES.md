# ZONOE 软件源 2026092434

## 更新内容

### Dylib 首次设备公钥登记改为 active UDID proof

- `/index/index/apiface` 在确认 UDID 已激活且未过期后签发短时 `auth_proof`。
- `/index/dylib_verify/challenge` 对首次 Device Key enrollment 校验 `auth_proof`，并绑定 UDID、Dylib、设备公钥与一次性 Challenge。
- `/index/dylib_verify/verify` 首次 enrollment 不再要求 `license_code`；P-256 canonical 纳入 `auth_proof`。
- 新版 iOS `ZONVerifyClient` 自动从 `/index/index/apiface?udid=...` 获取 proof，不需要保存已激活卡密。

### 兼容性

- 已存在 active Device Key 的旧 v3 客户端保留兼容路径。
- 只有首次登记新 Device Key 强制使用 active-UDID proof，不再回退旧 `license_code` enrollment。
- 保留 2432 `/unbind` UDID 1-128 字符支持、2431 验证记录原始 UDID/IP 与 offline_grace 继承、2430 Secretless Auth v3。

### 后台与日志

- 删除重复的 `dylib_api_test/index` Controller / View / JS；API 调试继续统一使用“系统配置 → API 接口测试”。
- 保留 `/index/dylib_verify/verify` `bad_request` 审计和验证记录 UDID/IP 能力。

## CI / 验证

`Dylib Auth Proof 2434 CI` 覆盖 PHP 7.0 syntax、auth_proof 服务端契约、iOS proof 自动获取、canonical 绑定、首次 enrollment 去 `license_code`、重复测试页清理及在线更新 manifest。

正式发布继续经过 canonical Release Gate：完整 PHP regression、source integrity、MySQL 5.7 migration、HTTP load 与在线更新 E2E。

## 升级路径

`source-v2026092433 -> source-v2026092434`
