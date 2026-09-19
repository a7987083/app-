# ZONOE 软件源 2026091909

## 更新内容

本版本基于 `2026091908`，将 IPA 管理中心网络请求重新收敛到原项目 FastAdmin / ThinkPHP 原生前端控制器路由模型，修复后台入口文件使用 PATH_INFO 时被当前 Nginx `try_files $uri =404` 直接拦截的问题。

### 1. IPA 管理中心 FastAdmin 路由收敛

- 保留现有 FastAdmin `Form.api.bindevent`、BootstrapTable 和 Controller action，不新增 Nginx 特殊 location。
- IPA 模块统一通过当前 `Config.moduleurl` 生成 ThinkPHP 原生 `?s=/controller/action` 请求。
- 覆盖 `ipa_center/*`、`ipa_lifecycle/*`、`ipa_recovery/*`、`ipa_production/*` 四组后台 AJAX。
- Setting 保存表单继续使用 FastAdmin Form 生命周期；AJAX 发出前统一归一化为原生前端控制器路由。
- 不修改 Authorization 中心及其他现有后台页面的请求路径。

### 2. OpenList 业务保持不变

- 保留 `IpaSourceConfig::testSaved()`、Token AES-256-CBC/HMAC 存储、保存后 round-trip 校验。
- OpenList API 继续固定 `/api/fs/list`、`/api/fs/get`，Authorization 继续发送原始 API Token，不添加 `Bearer`。
- IPA 根目录、目录缓存、Range Parser、绑定、治理、恢复和 Retention 业务逻辑不变。

### 3. 兼容与风险边界

- 不修改 Nginx、PHP-FPM 或 BaoTa 全局配置。
- 不修改 appstore/appstore_v2 wire protocol、授权判定、downloadURL 隔离和 Phase 20 schema。
- PHP 7.0 兼容保持不变。

### 4. CI / 发布验证

- PHP 7.0 lint / regression contracts。
- Phase 20 UI / OpenList HTTP E2E 与 updater rollback E2E。
- MySQL 5.7 migrations 双次幂等验证。
- `/appstore` release-gating concurrency matrix。
- GitHub Release package / SHA256 / online-update E2E。

### 发布与在线更新

- 版本由 `2026091908` 升级为 `2026091909`。
- 目标 GitHub Release 标签：`source-v2026091909`。
- `VERSION`、`public/update/ver.txt`、`ver.json` 同步更新到 `2026091909`。
- 本轮未修改 `UpdateIntegrity::files()` 中签名文件，`file_sign` 保持 `8be29c04c34ba1d1cc7ec77d392b2bee`。

### 发布后验证

在线更新到 `2026091909` 后：

- IPA 设置页点击“确定”不应再请求 `/FRKToHDckx.php/ipa_center/source_save` 这种 PATH_INFO 地址。
- Network 中应看到后台入口文件通过 `?s=/ipa_center/source_save` 进入 ThinkPHP。
- 首次保存后应创建 `runtime/ipa/.openlist-key` 与 `runtime/ipa/openlist.json`。
- 页面“当前”应变为“已配置”，随后“测试连接”应读取已保存配置并调用 OpenList `/api/fs/list`。
