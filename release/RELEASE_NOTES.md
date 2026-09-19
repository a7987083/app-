# ZONOE 软件源 2026091905

## 更新内容

本版本基于 `source-v2026091904`，针对 IPA 管理中心“OpenList 设置保存仍显示 error”的现场问题做审计驱动修复。

### 根因 1：保存/测试权限匹配名错误

- FastAdmin `Backend::_initialize()` 使用真实 action 名做权限匹配；`source_save` / `source_test` 会保留下划线。
- 2026091904 错误使用 `sourcesave` / `sourcetest` 写入 `$noNeedRight`，导致该豁免未命中。
- 对已有管理员组而言，如果只有 `ipa_center/setting` 权限而没有后续新增的隐藏 `ipa_center/source_save` / `ipa_center/source_test` 节点，请求会在进入控制器方法前被拒绝。
- 本版本改为精确 action 名 `source_save` / `source_test`，并继续在方法内部执行 `assertSettingRight()`，使保存/测试继承可见的 `ipa_center/setting` 权限，而不是无条件放行。

### 根因 2：FastAdmin 核心 JS 缓存未随 fast.js 更新失效

- RequireJS 使用 `site.version` 为 AMD 模块追加缓存参数。
- 原动态资源版本只扫描 `public/assets/js/backend/*.js` 的 mtime；2026091904 修改了 `public/assets/js/fast.js`，但没有修改 `backend/ipa_center.js`，因此浏览器可能继续使用 2026091903 的旧 `fast.js`。
- 旧 `fast.js` 的 HTTP error 分支只展示 `xhr.statusText`，因此现场仍只看到 `error`。
- 本版本将 `fast.js`、`require-backend.js`、`backend.js`、`backend-init.js` 纳入动态资源版本计算，核心 JS 变化会强制 RequireJS URL 版本变化。

### 错误可观测性

- `sourceSave()` / `sourceTest()` 改为捕获 PHP 7 `\Throwable`，覆盖 `Exception`、`Error`、`TypeError` 等运行时失败。
- 继续使用 2026091904 已加入的 FastAdmin Ajax 错误解析：优先读取 `responseJSON.msg` / `responseText`，最后才回退到 HTTP statusText。
- 结合新的缓存失效机制，生产浏览器将实际加载该错误解析代码。

### 存储模型

- 不回退 2026091904 数据库瘦身方案。
- OpenList 配置与加密 Token 继续保存于 `runtime/ipa/openlist.json`，不再写入 `fa_ipa_source`。
- `fa_ipa_source` 仍保留一次性旧配置导入兼容，不做破坏性 DROP。
- `fa_ipa_metadata`、`fa_ipa_binding`、`fa_ipa_scan_task`、`fa_ipa_governance_issue`、`fa_ipa_operation_log` 的长期存储策略保持不变。

### 发布与兼容性

- 不修改原 `ZONOE Source Release` workflow 语义。
- 在线更新包继续由 `release/online-update-files.txt` + `tools/build_online_update.php` 生成。
- 本次修改文件均已在现有在线更新 manifest 范围内。
- `application/common/behavior/Common.php` 属于 `UpdateIntegrity::files()` 监控文件，因此本版本完整性签名已重新计算为 `8be29c04c34ba1d1cc7ec77d392b2bee`。

### 已验证候选

- Regression Checks Run #245：通过。
- Phase14 Production Hardening Run #99：通过。
- Phase 20 IPA Management Run #116：contract / integration / package / MySQL 5.7 全部通过。
- 正式 Release 首次尝试由完整性门禁正确拦截旧 `file_sign`；已按 `UpdateIntegrity::signFromRoot()` 实际结果更新为 `8be29c04c34ba1d1cc7ec77d392b2bee`，重新进入正式发布链。
- 正式发布完成后仍需验证 `source-v2026091904 -> source-v2026091905` 在线更新 E2E。
