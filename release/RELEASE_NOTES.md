# ZONOE 软件源 2026091910

## 更新内容

本版本基于 `2026091909`，修正 IPA 管理中心上一版错误引入的 `?s=/controller/action` 路由适配器，并将整个 IPA 管理模块真正收敛回当前项目已经验证可用的 FastAdmin / ThinkPHP action 与 PATH_INFO 路由规范。

### 1. 整体回归原 FastAdmin 路由

- 撤销 IPA 模块自定义 `$.ajaxPrefilter` 与 `?s=/...` 请求转换。
- Setting 保存继续使用框架生成的 `{:url('ipa_center/source_save')}`，通过 FastAdmin `Form.api.bindevent` 提交。
- 其他 IPA AJAX 保持 `Fast.api.ajax({url:'controller/action'})` / BootstrapTable 原生路径，由 FastAdmin 统一转换成后台入口 PATH_INFO。
- 路由行为与生产上已验证正常的授权中心保持一致，例如 `/FRKToHDckx.php/authorization/events`。

### 2. Controller action 全量对齐

为所有前端 URL 与权限规则补齐同名 snake_case action，同时保留现有 camelCase 实现作为兼容目标：

- `ipa_center/*`：扫描、解析、元数据、绑定、写库、治理、网络源保存/测试全部覆盖。
- `ipa_recovery/*`：`scan_interrupted`、`retry`。
- `ipa_lifecycle/*`：`ignored_list`、`ignore_batch`、`unignore_batch`、`sweep_expired`。
- `ipa_production/*`：`metrics`、`retention_preview`、`retention_apply`。

snake_case action 只负责进入原业务实现，不复制业务逻辑，从而同时保证 FastAdmin URL/权限命名一致和旧内部调用兼容。

### 3. 业务逻辑保持不变

- 保留 `IpaSourceConfig::testSaved()`、Token AES-256-CBC/HMAC 存储、保存后 round-trip 校验。
- OpenList API 继续固定 `/api/fs/list`、`/api/fs/get`，Authorization 继续发送原始 API Token，不添加 `Bearer`。
- IPA 根目录、目录缓存、Range Parser、绑定、治理、恢复、Retention 与数据库结构不变。
- 不修改 appstore/appstore_v2 wire protocol、授权判定和 downloadURL 隔离。

### 4. 新增回归门禁

`phase20_ui_contract_test.php` 现在会明确验证：

- IPA JS 不允许注册 `$.ajaxPrefilter`。
- IPA JS 不允许强制 `?s=/` 路由。
- `ipa_center` / `ipa_recovery` / `ipa_lifecycle` / `ipa_production` 所有生产 URL 都必须存在同名 snake_case Controller action。
- Setting 页面继续使用框架生成 action、CSRF、validator 与 FastAdmin Form 生命周期。

### 5. 兼容与风险边界

- 不修改 Nginx、PHP-FPM 或 BaoTa 全局配置。
- 不修改授权中心等已经工作的后台模块。
- PHP 7.0 兼容保持不变。

### 6. CI / 发布验证

- PHP 7.0 lint / regression contracts。
- Phase 20 UI / OpenList HTTP E2E 与 updater rollback E2E。
- MySQL 5.7 migrations 双次幂等验证。
- `/appstore` release-gating concurrency matrix。
- GitHub Release package / SHA256 / online-update E2E。

### 发布与在线更新

- 版本由 `2026091909` 升级为 `2026091910`。
- 目标 GitHub Release 标签：`source-v2026091910`。
- `VERSION`、`public/update/ver.txt`、`ver.json` 同步更新到 `2026091910`。
- 本轮未修改 `UpdateIntegrity::files()` 中签名文件，`file_sign` 保持 `8be29c04c34ba1d1cc7ec77d392b2bee`。

### 发布后验证

在线更新到 `2026091910` 后：

- IPA 设置页点击“确定”应请求 `/FRKToHDckx.php/ipa_center/source_save`，而不是 `?s=/ipa_center/source_save`。
- `source_save` 应由 PHP/FastAdmin 正常返回 JSON，不再出现 Nginx 404。
- 首次保存后应创建 `runtime/ipa/.openlist-key` 与 `runtime/ipa/openlist.json`。
- 页面“当前”应变为“已配置”，随后“测试连接”应读取已保存配置并调用 OpenList `/api/fs/list`。
- 元数据、扫描、绑定、写库、治理、恢复、ignore lifecycle、Range metrics 和 Retention 的 AJAX 也应继续走相同 FastAdmin PATH_INFO 路由模型。
