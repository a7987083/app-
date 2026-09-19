# ZONOE 软件源 2026091908

## 更新内容

本版本基于 `2026091907`，正式发布 IPA 管理中心 OpenList 保存/测试链路修复，避免同一版本号重复刷新 Release 后，已升级服务器无法再次获取修复文件。

### 1. OpenList 保存/测试链路

- Setting 页恢复成熟仓库的已保存配置测试语义：先点击“确定”保存，再测试已经持久化并成功解密的 OpenList 配置。
- `IpaSourceConfig` 增加显式 `testSaved()` 路径；兼容入口统一转入已保存配置测试。
- 保存后执行配置文件读回与 Token 加密/解密 round-trip 校验，及时识别 runtime 写入或密钥异常。
- Token 留空时保留已保存 Token；首次配置没有 Token 时仍拒绝保存。
- 页面可安全显示已保存 Token 读取失败原因，但不会回显 Token 明文。
- 测试连接不再使用浏览器当前未保存表单参数，避免浏览器密码管理器自动填充与持久化状态混淆。

### 2. OpenList 协议保持不变

- API endpoint 继续固定使用 `/api/fs/list`、`/api/fs/get`。
- Authorization 继续发送 OpenList 固定 API Token，不添加 `Bearer` 前缀。
- IPA 内部根目录继续使用如 `/a/app` 的 OpenList 路径。
- 既有扫描、缓存、Range Parser、治理和权限边界保持不变。

### 3. 兼容与安全边界

- 不修改 appstore/appstore_v2 wire protocol、授权判定、paid `downloadURL` 裁剪、entitlement cache、动态公告 sentinel 和 Nuosike fallback。
- 不修改 Phase 20 数据库 schema。
- PHP 7.0 兼容语法保持不变。

### 4. CI / 发布验证

- PHP 7.0 lint / regression contracts。
- Phase 20 OpenList HTTP E2E 与 updater rollback E2E。
- MySQL 5.7 migrations 双次幂等验证。
- `/appstore` release-gating concurrency matrix。
- GitHub Release package build / SHA256 / online-update E2E。

### 发布与在线更新

- 版本由 `2026091907` 升级为 `2026091908`，确保已经安装旧 `2026091907` 的服务器能够重新检测并安装本轮最终 OpenList 修复。
- 目标 GitHub Release 标签：`source-v2026091908`。
- `VERSION`、`public/update/ver.txt`、`ver.json` 同步更新到 `2026091908`。
- 本轮未修改 `UpdateIntegrity::files()` 中签名文件，`file_sign` 保持 `8be29c04c34ba1d1cc7ec77d392b2bee`，正式 workflow 会再次严格校验。

### 发布后验证

在线更新到 `2026091908` 后：

- IPA 设置页底部应显示“请先点击‘确定’保存。测试连接只读取已经保存并成功解密的 OpenList 配置。”
- 首次填写 Token 并保存后，“当前”应显示已配置提示而非“未配置”。
- 再点击“测试连接”，应从已保存配置读取 Token 并调用 OpenList `/api/fs/list`。
- 如失败，应显示后端/OpenList 真实错误信息，不再仅显示泛化 `error`。
