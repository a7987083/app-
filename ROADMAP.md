# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release: `source-v2026092412`
- Release branch: `release/2026092412-api-integration-center`
- Release target: `c03c2d7deb7bbac21918ef126f4c50c700f2d838`
- Latest release-gate code head: `004de0dee04cf1c7ca21abf1e1ab693823c1ddd7`
- OC Codegen CI #14 / Run `36239192712`: SUCCESS
- ZONOE Source Release #253 / Run `36239620864`: SUCCESS
- IPA Online Update Release Gate #152 / Run `36239878997`: SUCCESS

## 2026092412 — API Integration Center

### 定位

Dylib 验证中心只负责验证 API、签名协议、结果码、运行配置、验证记录和接入文档。UDID 获取、卡密输入、公告弹窗、悬浮窗、授权信息页等客户端 UI 不属于验证中心，由实际 OC/Swift 客户端工程自行实现。

### 已完成

- [x] 新增 `DylibApiContract`，集中描述真实请求字段、真实返回字段、v1/v2 canonical、action 与 result code。
- [x] 保留现有字符串 `code` 协议，不另造会破坏兼容性的数字错误码。
- [x] Generator 升级为 `2.2.0`，生成 OC 明确定位为测试/参考实现。
- [x] 生成包新增 `API_REFERENCE.md`、`ERROR_CODES.md`、`EXAMPLES.md`。
- [x] 后台“接入说明”升级成详细 API 文档页。
- [x] 客户端处理原则明确为 `ok + code`；`message` 只作为可展示文本。
- [x] 验证路径文档和 Release Gate 支持动态 `runtimeConfig.verify_path`，不再硬编码默认路径。
- [x] 在线更新 manifest 包含 `DylibApiContract.php`。
- [x] PHP 7.0 / JavaScript / Codegen contract / UX contract / MySQL 5.7 全部通过。
- [x] 正式 GitHub Release 已发布。
- [x] Real GitHub Release online-update E2E 已通过。
- [x] 正式 IPA Online Update Release Gate 已通过。

## 后续增强

- 让后台 API 文档完全由 `DylibApiContract` 动态渲染，消除静态文档漂移。
- 可增加纯 API 调试器，但不得把客户端 UI 流程混入验证中心。
- 可增加独立 OC/Swift 示例项目做真实客户端接入验收；当前 CI 不宣称第三方客户端已人工验证。
