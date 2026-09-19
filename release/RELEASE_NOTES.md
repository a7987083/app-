# ZONOE 软件源 2026091902

## 更新内容

本版本是 Phase 20 OpenList 设置与连接诊断 Hotfix，基线为 `source-v2026091901`。

### OpenList 设置修复

- 设置页统一采用 OpenList 官方术语“令牌”，并明确位置为 OpenList「设置 → 其他 → 令牌」。
- OpenList API 前缀固定由程序使用 `/api`，不再在后台暴露 `API Base` 让管理员修改，避免把 API 路径与 OpenList 内部目录概念混淆。
- 保留 `fa_ipa_source.api_base` 字段用于旧数据兼容，但新保存配置始终写入 `/api`。
- OpenList 地址只填写站点根地址，例如 `https://yun.zonoeios.xyz`；IPA 根目录独立填写，例如 `/a/app`。
- 公开下载地址前缀继续兼容 `https://yun.zonoeios.xyz/d` 和旧 `{path}` 模板。

### 保存与测试

- 新增“保存并测试”流程：先保存当前页面输入，再使用刚保存的配置执行 `/api/fs/list` 健康检查，避免旧版“页面改了但测试数据库旧配置”的歧义。
- OpenList 令牌输入框改用独立字段名并增加密码管理器忽略标记，降低浏览器将后台登录密码误填为 OpenList 令牌的风险。
- 已保存令牌仍只显示尾号提示，页面不会回显明文令牌。

### 错误诊断

- 设置页不再只依赖 FastAdmin 对 HTTP transport error 的 `statusText`。
- 保存/测试失败时优先显示后端 JSON `msg`，否则显示响应正文摘要和 HTTP 状态，避免线上只看到通用 `error`。
- 正常的 OpenList/API 错误仍由现有服务端异常链返回，不改变扫描、解析、绑定或治理逻辑。

### OpenList 官方语义

- 认证继续使用 `Authorization: <令牌>`，不添加 `Bearer` 前缀。
- 文件发现继续使用 OpenList `/api/fs/list`，参数保持 `path/password/page/per_page/refresh`。
- Phase 20 OpenList HTTP 集成测试继续覆盖 Authorization、list/get/rename/move 和递归 IPA 发现。

### 兼容性

- 不新增数据库 migration；继续使用 2026091901 已发布的 Phase 20 表结构。
- 不修改 `UpdateIntegrity::files()` 监控的关键文件，`file_sign` 继续为 `f3f6e072f814d06403ce5e393967c9e2`。
- 在线更新包仍由 `release/online-update-files.txt` + `tools/build_online_update.php` 生成。
- 正式发布继续统一走原 `ZONOE Source Release`，未新增独立发布流程或人工门禁。

### 验证

- Phase 20 UI/settings contract：通过。
- PHP syntax / Phase 20.0～20.7 contracts：通过。
- OpenList HTTP E2E：通过。
- MySQL 5.7 Phase 20 migrations ×2 回归：通过。
- updater rollback 与 legacy rollback regression：通过。
- Phase 20 在线更新 ZIP/SHA256/package content：通过。
- Hotfix PR #10 的 Phase 20 Run #92、Regression Checks PHP 7.0、Phase14 PHP 7.0 regression：通过。
