# ZONOE 软件源 2026092412

## 更新内容

### Dylib API 接入中心

本版本以 `source-v2026092411` 为升级基线，将 Dylib 验证中心的职责明确收敛为 **API 验证服务与接入文档中心**。

### 职责边界

验证中心负责：

- Dylib 注册与允许版本；
- `/index/dylib_verify/config` 运行配置发现；
- `/index/dylib_verify/verify` 在线验证；
- Protocol v1 / v2 HMAC-SHA256 canonical；
- Verify Secret；
- 稳定字符串 `code`、`action`、`message` 与返回字段；
- `permissions`、`access_level`、`notice`、`app_update` 等 API 数据；
- 验证日志和 API 接入说明。

验证中心不负责：UDID 输入/采集界面、卡密输入窗口、公告弹窗、悬浮窗、授权信息页或其它客户端产品 UI。这些由实际 OC/Swift 客户端工程自行实现。

### Canonical API contract

新增 `application/common/library/Ipa/DylibApiContract.php`，集中记录当前真实协议：

- v1/v2 请求字段；
- 返回字段；
- HMAC canonical 字段顺序；
- `allow / disable_feature / show_message / block` action；
- 当前真实 success/error string codes 及客户端建议。

2412 不引入新的数字错误码，继续使用现有字符串 `code`，避免破坏已经存在的客户端协议。

### API 接入说明

后台的“接入说明”升级成详细 API 文档页，直接提供：

- Endpoint 与请求方式；
- 请求字段、类型、必填条件和 v1/v2 差异；
- 返回字段与用途；
- Protocol v1/v2 canonical；
- HMAC-SHA256 规则；
- 错误码与客户端建议；
- 推荐接入顺序和职责边界。

客户端程序逻辑应使用 `ok + code`；`message` 是服务器返回的可展示文字，可原样显示，但不应解析 message 文本判断业务状态。

### API 接入示例生成器 2.2.0

原 OC Codegen 重新定位为测试/参考工具，而非完整客户端生成器。

生成包现在包含：

- `*DylibConfig.h/.m`
- `*DylibVerify.h/.m`
- `GeneratedConfig.json`
- `INTEGRATION.md`
- `API_REFERENCE.md`
- `ERROR_CODES.md`
- `EXAMPLES.md`
- `generation-manifest.json`

实际客户端可以完全不使用这些生成 OC 文件，直接按照 API 文档自行搭建。

### 兼容性

2412 不改变：

- v1 canonical；
- v2 canonical；
- `/index/dylib_verify/config`；
- `/index/dylib_verify/verify`；
- 当前字符串 result code wire protocol；
- Runtime Config 签名逻辑；
- 数据库表结构；
- 旧 `Index::dylib()` / `Index::apiface()` 兼容接口。

Legacy `Index::apiface()` 的签名协议仍与新的 per-Dylib Verify Secret 验证协议分离，不得混用。

### 开发验证

- OC Codegen CI #14 / Run `36239192712`：success。
- PHP 7.0 lint/contracts：success。
- JavaScript syntax：success。
- API reference / error-code generation contract：success。
- Dylib Center UX contract：success。
- Online-update package content gate：success。
- MySQL 5.7 migration regression：success。

目标升级路径：`source-v2026092411 -> source-v2026092412`。
