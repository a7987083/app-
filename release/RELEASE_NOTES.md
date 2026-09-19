# ZONOE 软件源 2026091911

## 更新内容

本版本基于 `2026091910`，继续完成 IPA 管理中心对原项目 FastAdmin / ThinkPHP 生命周期的收口。`2026091910` 已经修复 URL/action 命名并恢复 PATH_INFO 路由，本版本修复第二层响应控制流问题：业务 `try/catch` 不能捕获 FastAdmin/ThinkPHP 自己通过 `HttpResponseException` 抛出的 `$this->success()` 响应。

### 1. IPA 全模块 FastAdmin Response Lifecycle 修复

- `IpaCenter`、`IpaRecovery`、`IpaLifecycle`、`IpaProduction` 全部统一为：`try/catch` 只包业务调用，`$this->success()` 放在 `catch` 之后。
- 修复成功响应被 `catch (\Exception)` / `catch (\Throwable)` 截获后再次转成 `$this->error('')`，导致前端收到 `code:0, msg:""` 的问题。
- Setting 保存成功后现在应直接返回标准 FastAdmin JSON：`code:1`，而不是空错误响应。
- `source_test`、扫描、解析、绑定、治理、批量治理、写库、恢复、ignore lifecycle、Range metrics、Retention 等同类接口一并处理，不只修 `source_save`。

### 2. 保持 2026091910 路由规范

- 继续使用与授权中心一致的 `/FRKToHDckx.php/controller/action` FastAdmin PATH_INFO 模型。
- 保留 `ipa_center/*`、`ipa_recovery/*`、`ipa_lifecycle/*`、`ipa_production/*` 的 snake_case action。
- 不恢复 `$.ajaxPrefilter`，不恢复 `?s=/controller/action` workaround。
- Setting 继续使用 `{:url('ipa_center/source_save')}` + `Form.api.bindevent`。

### 3. 错误与验证语义保持清晰

- 业务异常继续进入 `$this->error($e->getMessage())`，保留真实错误消息。
- `writebackRandomPreview` 在业务 `try` 内的验证失败改为抛 `RuntimeException`，避免在 `try` 内直接调用 `$this->error()` 后被本方法自己的 `catch` 再次截获。
- `sourceSave()` / `sourceTest()` 仍保留 PHP 7 `Throwable` 捕获，成功响应移出 `try` 后不会再误捕获 FastAdmin 跳转异常。

### 4. 新增回归门禁

`phase20_ui_contract_test.php` 新增约束：

- 四个 IPA Controller 中，业务 `try` 块内禁止调用 `$this->success()`。
- `source_save` 与 `source_test` 必须保留明确的成功响应。
- `writebackRandomPreview` 的业务校验必须通过领域异常进入统一错误出口。
- 继续校验 1910 已加入的原生 FastAdmin action、PATH_INFO 路由和禁止 `?s=/` workaround 契约。

### 5. 业务逻辑保持不变

- OpenList 保存、Token AES-256-CBC/HMAC、保存后 round-trip、`testSaved()` 不变。
- OpenList `/api/fs/list`、`/api/fs/get`、原始 Token Authorization 不变。
- Range Parser、绑定、治理、恢复、Retention、数据库结构不变。
- 不修改 appstore/appstore_v2 wire protocol、授权判定和 downloadURL 隔离。
- 不修改 Nginx、PHP-FPM 或 BaoTa 全局配置。

### 6. 发布与在线更新

- 版本由 `2026091910` 升级为 `2026091911`。
- 目标 GitHub Release 标签：`source-v2026091911`。
- `VERSION`、`public/update/ver.txt`、`ver.json` 同步更新到 `2026091911`。
- 本轮未修改 `UpdateIntegrity::files()` 中签名文件，`file_sign` 保持 `8be29c04c34ba1d1cc7ec77d392b2bee`。

### 发布后验证

在线更新到 `2026091911` 后：

- IPA 设置页“确定”仍应请求 `/FRKToHDckx.php/ipa_center/source_save`。
- 保存成功应返回 JSON `code:1`，消息为 `IPA 网络源已保存`。
- 首次保存后应生成 `runtime/ipa/.openlist-key` 和 `runtime/ipa/openlist.json`。
- 页面重新加载后“当前”应显示“已配置”。
- 点击“测试连接”成功应返回 `code:1` 和 `OpenList 连接正常`，并读取已保存配置调用 `/api/fs/list`。
