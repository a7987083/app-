# ZONOE 软件源 2026092429

## 更新内容

### Dylib 验证中心：Secretless Auth

本版本以 `source-v2026092428` 为正式发布基线，完成 Dylib 验证中心断代式认证重构，不保留旧 Verify Secret / HMAC 客户端兼容链。

2026092429 的核心变化：

- 删除客户端嵌入式 `Verify Secret`、`verify_secret_ciphertext` 与相关后台 UI / Codegen / 文档路径；
- 删除旧请求 HMAC 验证和旧 nonce 表依赖；
- 新增设备密钥认证服务 `DylibDeviceAuthService`；
- 新增一次性 Challenge 与设备公钥登记表；
- 运行时验证切换为 Challenge + Device Key 签名；
- Bootstrap / Runtime Config 改为服务器非对称签名，客户端内置服务器公钥验签；
- 为 PHP 7.0 / OpenSSL 兼容，服务器配置签名使用 RSA-2048 + SHA-256；
- iOS 13 客户端使用 Security.framework 校验 RSA PKCS#1 v1.5 SHA-256 签名；
- 保留现有 UDID、卡密、黑名单、App Identity、Dylib version/build/SHA256、权限、公告、更新、offline grace 与短期 session token 业务模型；
- 旧客户端不再受支持，旧 Verify Secret 客户端在 2429 服务端切换后应视为失效客户端。

### 数据库迁移

新增 `release/sql/2026092429_secretless_dylib_auth.sql`：

- 创建设备公钥表；
- 创建一次性认证 Challenge 表；
- 删除 `fa_dylib.verify_secret_ciphertext`；
- 删除旧 `fa_dylib_nonce`；
- 提升 Dylib runtime config 协议版本；
- SQL 已按 MySQL 5.7 进行可重复执行验证。

### CI / 验证

2429 专用 CI 已覆盖：

- PHP 7.0 全部 Secretless Auth 核心文件语法检查；
- 验证客户端/服务端核心实现中不再残留 `verify_secret` / `verifySecret`；
- RSA-2048 + SHA-256 OpenSSL 签名/验签自检；
- MySQL 5.7 从 2428 Dylib schema fixture 执行 2429 migration 两次并验证最终 schema；
- iOS 13 arm64 Objective-C 客户端 `clang -fsyntax-only`。

当前正式发布仍以完整 canonical release workflow、在线更新包完整性检查和 GitHub Release 结果为最终发布证据。

## 升级路径

`source-v2026092428 -> source-v2026092429`
