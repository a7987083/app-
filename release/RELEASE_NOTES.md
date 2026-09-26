# ZONOE 软件源 2026092413

## 更新内容

### Dylib API 完整目录与下载

本版本以 `source-v2026092412` 为升级基线，将 Dylib 验证中心的 API 接入说明从局部验证协议文档扩展为完整 API 目录，并提供可直接下载的完整接入资料包。

### 8 个现有入口

后台 `Dylib 验证中心 -> API / 高级 -> API 接入说明` 现在明确列出 8 个现有入口，并标注接口类型：

- `POST /authorization`：授权查询页面兼容入口；
- `GET /appstore`：软件源/激活相关入口；
- `GET /index/index/apiface`：Legacy 授权校验；
- `GET /index/index/dylib`：Legacy Dylib 配置；
- `POST /unbind`：换绑页面兼容入口；
- `GET /unbind/query`：换绑状态 JSON 查询；
- `GET /index/dylib_verify/config`：Runtime Config；
- `POST <runtime verify_path>`：Dylib 在线验证。

特别说明：`/authorization` 与 `/unbind` 当前由 Controller 渲染 HTML 页面，不能作为纯 JSON API 直接解析。2413 在文档中明确区分 JSON API、页面兼容入口与 Legacy 接口。

### 独立签名协议

签名说明从接口列表中独立成专门分页，继续使用现有 Protocol v1 / v2 HMAC-SHA256 canonical。2413 不改变请求字段顺序、Verify Secret 使用方式或现有 wire contract。

### 全部下载 ZIP

选择当前 Dylib 后，可在 API 接入说明右上角使用“全部下载 ZIP”。下载包按当前 Dylib 与运行配置动态生成，固定包含 13 个文件：

- `README.md`
- `API_OVERVIEW.md`
- `API_REFERENCE.md`
- `ERROR_CODES.md`
- `SIGNATURE.md`
- `RESPONSE_MODEL.md`
- `FLOW.md`
- `examples/curl.md`
- `examples/Objective-C.md`
- `examples/Swift.md`
- `examples/Python.md`
- `schemas/api.json`
- `schemas/error_codes.json`

真实 Verify Secret 不会写入下载资料；示例统一使用 `<VERIFY_SECRET>` 占位符。

### 统一文档数据源

新增 `application/common/library/Ipa/DylibApiDocumentation.php`，网页展示和 ZIP 导出共用同一份协议描述；新增 `application/admin/controller/DylibApiDocs.php` 负责后台下载。两者均已加入在线更新 manifest。

### 自动验证

2413 新增 Dylib API 文档生成契约，真实调用文档生成器检查：

- 8 个入口数量；
- 13 个导出文件；
- 动态 `verify_path`；
- `api.json` / `error_codes.json` 可解析；
- 页面兼容入口类型标注；
- 导出内容不包含真实 Verify Secret。

开发 CI：OC Codegen CI #17 / Run `36250685197` 全部成功；PHP 7.0、Dylib Center UX、API documentation contract、online-update package gate 与 MySQL 5.7 migration regression 均通过。

### 兼容性

2413 不改变：

- Protocol v1 / v2 canonical；
- Dylib 在线验证 wire contract；
- Runtime Config 签名；
- 数据库结构；
- Legacy `Index::dylib()` / `Index::apiface()` 行为；
- 2412 的 Objective-C 测试/参考代码定位。

目标升级路径：`source-v2026092412 -> source-v2026092413`。
