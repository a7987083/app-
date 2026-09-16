# ZONOE 软件源 2026091602

## 更新内容

- 修正 2026091601 的错误 DES-CBC 假设，按 zonoe/QNQ 实际客户端解码协议重做软件源加密。
- `appstore` 改为 Nuosike legacy 兼容链路：从 `update.json` / `key.json` 解析当前动态 `bkey`，使用 RC4 加密并 Base64 输出。
- `appstore_v2` 改为 Nuosike v2 兼容链路：15 字符 RC4 key + RSA PKCS#1 v1.5 + `0xFEEDFACF` 容器 + Nuosike 自定义变宽字符编码。
- 新增与 Nuosike 同名的公开入口：`/api.php`（appstore）和 `/encrypt.php`（appstore_v2），均接收 POST `content` 参数并直接返回密文字符串。
- `/appstore` 主接口继续保留现有 `appstore` / `appstore_v2` 外层字段和请求头识别方式，认证、UDID、卡密、黑名单、授权时长与换绑次数语义不变。
- 默认仍为本地优先、Nuosike 故障回退；可用 `SOURCE_ENCRYPTION_PROVIDER=nuosike` 整体回退，或 `SOURCE_ENCRYPTION_LOCAL_V2=0` 单独关闭 v2 本地编码。
- PHP 7.0 CI 已改为验证 RC4/RSA/v2 容器协议，不再使用 DES-CBC 兼容测试。

## 兼容性与依赖

- 目标环境继续兼容宝塔 PHP 7.0。
- `appstore_v2` 的编码可在本机完成；`appstore` 为保持现有 zonoe 客户端 1:1 协议，仍需读取 Nuosike `update.json` 与 `key.json` 的当前动态 key 链。
- 不修改现有数据库结构和已有卡密数据。
- 不修改在线更新器的备份、回滚与 SHA256 校验机制。
