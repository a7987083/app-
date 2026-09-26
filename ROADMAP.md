# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release: `source-v2026092413`
- Release branch: `release/2026092413-api-doc-full-catalog`
- Release target / tested release head: `acfe570bef7113ae3dcf94b33ce8258f75ad33d7`
- OC Codegen CI #17 / Run `36250685197`: SUCCESS
- ZONOE Source Release #254 / Run `36251418295`: SUCCESS
- IPA Online Update Release Gate #153 / Run `36251418307`: SUCCESS
- Real GitHub Release online-update E2E: SUCCESS

## 2026092413 — Dylib API Full Catalog

### 已完成

- [x] API 接入说明扩展为 8 个现有入口的完整目录。
- [x] 明确区分 JSON API、HTML 页面兼容入口和 Legacy API。
- [x] 签名协议独立展示，保持 Protocol v1/v2 HMAC-SHA256 wire contract 不变。
- [x] 新增“全部下载 ZIP”，按当前 Dylib 和动态 `verify_path` 生成接入资料。
- [x] 下载包固定输出 13 个文件：API 文档、签名、错误码、流程、OC/Swift/Python/cURL 示例和 JSON schema。
- [x] 下载资料不导出真实 Verify Secret，统一使用 `<VERIFY_SECRET>` 占位符。
- [x] 新增 `DylibApiDocumentation.php` 和 `DylibApiDocs.php`，并纳入在线更新 manifest。
- [x] 新增真实文档生成契约：8 个入口、13 个文件、动态 verify_path、JSON 可解析、Secret 不泄漏。
- [x] PHP 7.0、MySQL 5.7、HTTP load、在线更新包、Release Gate、真实 GitHub Release E2E 全部通过。
- [x] 正式 Release `source-v2026092413` 已发布。

## 后续增强

- 后台 HTML 仍有部分静态说明，应继续向统一文档模型收敛，避免展示层与协议源漂移。
- 可增加真正的 API 调试器，但不能把 UDID/卡密/弹窗等客户端 UX 混回验证中心。
- 可用独立 OC/Swift 测试客户端做人工接入验收；当前自动化不等于真机第三方客户端验收。
