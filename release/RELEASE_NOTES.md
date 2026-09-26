# ZONOE 软件源 2026092410

## 更新内容

本版本以 `source-v2026092409` 为升级基线，修复 Dylib 验证中心中三个实际使用问题：Dylib 无法删除、版本管理缺少编辑/删除、OC Codegen 未暴露旧版 `Index::dylib()` / `Index::apiface()` 接口能力。

### Dylib 删除

- 去掉“已有版本/旧授权/验证记录时禁止物理删除”的限制。
- 删除 Dylib 时使用数据库事务清理：
  - `dylib_version`
  - `dylib_app_binding`
  - 对应 `dylib_verify_log`
  - Dylib 主记录
- 后台删除提示明确标注该操作会永久清理关联历史。

### 版本管理

- 版本列表新增“编辑”和“删除”。
- 编辑复用 `saveVersion(id)`，可修改 Dylib、版本号、Build、状态、SHA256、文件大小、离线容错、失败动作和客户端提示。
- 新增 `deleteVersion()`，支持物理删除版本记录。

### OC 接入代码

- Objective-C Generator 升级到 `2.1.0`。
- 继续自动读取 Dylib、版本、Bootstrap、API Endpoint、验证密钥。
- 在生成配置中增加旧接口兼容 URL：
  - `Index::dylib()` -> `/index/index/dylib`
  - `Index::apiface()` -> `/index/index/apiface`
- 生成的 Objective-C Config 新增：
  - `legacyDylibURLs`
  - `legacyApiFaceURLs`
- `GeneratedConfig.json` 与 `INTEGRATION.md` 同步输出旧接口信息。

### 兼容性

2410 不修改：

- `/index/dylib_verify/config`
- `/index/dylib_verify/verify`
- v1 / v2 HMAC canonical
- Runtime Config 签名协议
- 权限模型
- 2409 的 5 Tab Dylib Center 信息架构
- MySQL 表结构

因此 2409 已接入的新验证客户端无需重新适配；需要旧 `Index::dylib()` / `Index::apiface()` 的工程可以直接从 2410 Codegen 获取兼容地址。

### 已完成验证

- `OC Codegen CI #9` / Run `36230506336`：success。
- PHP 7.0 lint：success。
- JavaScript syntax check：success。
- OC Codegen contract：success，新增 legacy API URL contract。
- Dylib Center UX contract：success。
- MySQL 5.7 migration regression：success；2410 无新增 migration。

目标升级路径：`source-v2026092409 -> source-v2026092410`。
