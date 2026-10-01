# ZONOE 软件源 2026092430

## 更新内容

### Dylib 验证中心：从 2026092428 基线重做 Secretless Auth

本版本直接以 `source-v2026092428` 为基线重新开发，不继承 2429 的精简实现。目标是：**2428 完整功能集合保持不变，只替换 Verify Secret / HMAC 认证机制。**

### 保留 2428 完整功能

以下能力继续保留：Dylib 注册/编辑/启停/删除、版本管理与编辑、Runtime Config、远程通知、验证日志筛选/删除、Codegen Preview/ZIP、API 文档 ZIP、Legacy API URL、App Identity、卡密/UDID/黑名单、权限等级、App Update、offline grace、session token、Dylib Version/Build/SHA256/fail_action。

原有 API 继续存在：

- `POST /authorization`
- `GET /appstore`
- `GET /index/index/apiface`
- `GET /index/index/dylib`
- `POST /unbind`
- `GET /unbind/query`
- `GET /index/dylib_verify/config`
- `POST /index/dylib_verify/verify`

并新增：

- `POST /index/dylib_verify/challenge`

### Secretless Protocol v3

- 删除客户端 Verify Secret 和 `verify_secret_ciphertext`；
- 删除 Dylib Verify 请求 HMAC 认证；
- 每台设备生成独立 P-256 Keychain 密钥；
- 服务端每次签发短期一次性 Challenge；
- 客户端使用 ECDSA P-256 SHA-256 对固定 canonical 文本签名；
- 首次设备公钥绑定必须提供已经激活且绑定同一 UDID 的现有卡密；
- 后续请求使用已绑定公钥验证；
- Challenge 一次性消费并带过期时间，防止重放。

### Runtime Config 签名

Runtime Config 不再使用客户端共享 Secret 验签，改为服务器 RSA-2048/SHA-256 签名。客户端只嵌入服务器公钥与 Key ID。

服务器 RSA 私钥仅保存在服务器 `runtime/` 私有文件中，不写入数据库，也不再使用 `signing_private_key_ciphertext` 字段，从结构上消除生产环境因缺字段导致的 `fields not exists:[signing_private_key_ciphertext]` 问题。

### 数据库迁移

`2026092430_secretless_dylib_auth.sql`：

- 新增 `fa_dylib_device_key`；
- 新增 `fa_dylib_auth_challenge`；
- 删除 `fa_dylib.verify_secret_ciphertext`；
- 删除旧 `fa_dylib_nonce`；
- 不新增任何服务器签名私钥数据库字段；
- MySQL 5.7 下支持重复执行。

## CI / 验证

`Dylib Secretless Auth 2430 CI` 覆盖：

- PHP 7.0 / JS syntax；
- Secretless v3 核心断言；
- 2428 功能保留断言；
- RSA-2048 OpenSSL sign/verify；
- iOS 13 arm64 Objective-C syntax；
- MySQL 5.7 migration 双次执行；
- 在线更新 ZIP 实际构建和关键文件检查。

canonical Release 还会执行完整 PHP 7.0 regression、source integrity/file_sign、真实 MySQL 5.7 migration、/appstore HTTP 并发 gate，以及真实 GitHub Release 在线更新 E2E。CI 成功仅证明代码、迁移和发布链满足契约；真机 Challenge -> Device Sign -> Verify 运行链仍需独立真机证据。

## 升级路径

`source-v2026092428 -> source-v2026092430`
