# ZONOE 软件源 2026091903

## 更新内容

本版本是 Phase 20 OpenList 设置按钮 Hotfix，基线为 `source-v2026091902`。

### 设置页按钮修复

- 修复 OpenList 设置页“保存 / 保存并测试”按钮可见但点击无响应的问题。
- 根因是 2026091902 模板改用了 `.btn-ipa-source-save-v2` / `.btn-ipa-source-save-test-v2` 并依赖局部页面内联 `<script>` 绑定，而现有 FastAdmin `IpaCenter` 控制器只绑定 `.btn-ipa-source-save` / `.btn-ipa-source-test`。
- 设置页恢复使用现有 FastAdmin Controller 生命周期绑定，不再依赖局部 HTML 中的内联脚本执行。
- 按钮恢复为“保存 / 测试连接”；修改地址、目录或令牌后先保存，再执行连接测试。
- OpenList 令牌继续使用官方长期令牌语义，认证仍为 `Authorization: <令牌>`，不加 `Bearer`。
- 令牌输入继续使用 `autocomplete="new-password"`、`data-lpignore` 等属性降低浏览器密码管理器误填风险。

### OpenList 配置语义

- OpenList 地址仍只填写站点根地址，例如 `https://yun.zonoeios.xyz`。
- OpenList API 前缀继续固定为 `/api`，后台不再暴露 `API Base`。
- IPA 根目录继续独立填写，例如 `/a/app`。
- 公开下载地址前缀继续兼容 `https://yun.zonoeios.xyz/d` 和旧 `{path}` 模板。

### 兼容性

- 不新增数据库 migration；继续使用 2026091901 已发布的 Phase 20 表结构。
- 不修改扫描、Range Parser、绑定、治理、Retention 或在线更新架构。
- 不修改 `UpdateIntegrity::files()` 监控的关键文件，`file_sign` 继续为 `f3f6e072f814d06403ce5e393967c9e2`。
- 在线更新包继续由 `release/online-update-files.txt` + `tools/build_online_update.php` 生成。
- 正式发布继续统一走原 `ZONOE Source Release`。

### 验证

- Phase 20 UI contract：通过，并新增“设置页不得依赖内联脚本、按钮必须存在 FastAdmin Controller 绑定”的约束。
- Phase 20 Run #98：contract / MySQL 5.7 / OpenList HTTP integration / package 全部通过。
- Regression Checks Run #227：通过。
- Phase14 Production Hardening Run #81：通过。
