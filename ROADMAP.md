# ZONOE 软件源开发路线图

## 当前基线

- Repository: `a7987083/app-`
- Stable release: `source-v2026092411`
- 2411 release branch: `release/2026092411-dylib-center-ux`
- 2411 release target: `b46da637df7c0c7918b4ec71b6fe2bdf2387a73c`
- 2411 latest tested branch head: `56f503c9eb094fe02cdc9987f25cd12edbf065f0`
- Current development branch: `feature/2026092412-api-integration-center`
- Verified 2412 checkpoint: `fc5a3d90398df60f2c651a113c253dce67e8d4db`
- OC Codegen CI #14 / Run `36239192712`: SUCCESS.

## 2026092412 — API Integration Center

### 定位

Dylib 验证中心只负责验证 API、签名协议、结果码、运行配置、验证记录和接入文档。UDID 获取、卡密输入、公告弹窗、悬浮窗、授权信息页等客户端 UI 不属于验证中心，由实际 OC/Swift 客户端工程自行实现。

### 已完成

- [x] 新增 `DylibApiContract`，集中描述真实请求字段、真实返回字段、v1/v2 canonical、action 与 result code。
- [x] 保留现有字符串 `code` 协议，不另造会破坏兼容性的数字错误码。
- [x] Generator 升级为 `2.2.0`，生成的 OC 代码明确定位为测试/参考实现。
- [x] 生成包新增 `API_REFERENCE.md`、`ERROR_CODES.md`、`EXAMPLES.md`。
- [x] `API_REFERENCE.md` 说明 POST/JSON/Form、字段类型、必填条件、HMAC-SHA256 canonical 和返回结构。
- [x] `ERROR_CODES.md` 说明当前实际 code、含义和客户端处理建议。
- [x] 后台“接入说明”升级为 API 文档页，包含接口、请求参数、返回字段、错误码和接入原则。
- [x] 页面明确：客户端按 `ok + code` 判断业务，`message` 可展示但不能作为程序逻辑条件。
- [x] 在线更新 manifest 已包含 `DylibApiContract.php`。
- [x] PHP 7.0 / JavaScript / Codegen contract / UX contract / package gate / MySQL 5.7 均通过开发 CI。

### 下一步

- [ ] 创建 `release/2026092412-api-integration-center`。
- [ ] 更新 VERSION / ver.txt / ver.json / Release Notes 为 `2026092412`。
- [ ] 运行 ZONOE Source Release 与 IPA Online Update Release Gate。
- [ ] 验证真实 GitHub Release E2E。
- [ ] 发布 `source-v2026092412` 及 `zonoe-online-update.zip` / `.sha256`。

## 后续增强

- 让后台 API 文档完全从 `DylibApiContract` 动态渲染，进一步消除文档与服务端协议漂移风险。
- 可增加纯 API 调试器，但不得把客户端 UI 流程混入验证中心。
