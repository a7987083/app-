# ZONOE 软件源 2026091601

## 更新内容

- 软件源加密从外部 Nuosike 改为本地兼容实现，`appstore` 与 `appstore_v2` 默认均优先本地处理。
- 保留 Nuosike 作为故障回退路径；本地加密异常时自动回退，不影响现有软件源可用性。
- 保持 `appstore` / `appstore_v2` 外层响应字段和现有客户端请求头识别方式不变。
- 保持现有 UDID、卡密、授权时长、黑名单和换绑次数语义不变，软件源认证继续复用现有授权链路。
- 新增本地 DES-CBC/PKCS7/Base64 兼容 Provider、策略层、PHP 7.0 兼容测试与差分探针工具。
- `SOURCE_ENCRYPTION_PROVIDER=nuosike` 可整体切回外部服务；`SOURCE_ENCRYPTION_LOCAL_V2=0` 可单独关闭 v2 本地加密。

## 兼容性

- 目标环境继续兼容宝塔 PHP 7.0。
- 不修改现有数据库结构和已有卡密数据。
- 不修改在线更新器回滚机制。
- GitHub 在线更新继续强制 HTTPS + SHA256。
