# ZONOE 软件源 2026091901

## 更新内容

本版本发布 Phase 20 IPA Management Center，基线为 `source-v2026091809`。

### IPA 管理中心

- 新增 Phase 20 管理中心、任务、元数据、绑定、写回、数据治理和设置页面。
- 新增 OpenList 增量扫描，支持路径规范化、目录缓存以及 new / changed / unchanged / missing 分类。
- 扫描任务和任务项支持 checkpoint、失败状态和后续恢复。

### IPA HTTP Range 解析

- 通过 HTTP Range 读取 ZIP Central Directory 和 `Payload/*.app/Info.plist`，避免下载完整 IPA。
- 提取应用名称、Bundle ID、版本、Build、最低 iOS、图标候选、Mach-O 架构、Framework/dylib/appex、Swift 和 embedded.mobileprovision 等元数据。
- 支持 MD5/内容身份复用、parser version 和 Range 请求/流量统计。

### IPA 绑定与写回

- 建立 IPA 元数据与 `fa_category` 的持久化一对一绑定。
- 自动绑定仅接受确定性条件，名称/版本等模糊候选只用于人工判断。
- 新增全局写回模板，支持 `name`、`nickname`、`image`、`bt1a`、`bt2a`、`keywords` 白名单字段。
- `keywords` 使用受控 `IPA_META_START` / `IPA_META_END` 区块，避免重复追加并保留人工文本。

### 数据治理与生产增强

- 数据治理采用 preview → confirm → execute → verify，并使用 `plan_hash` 拒绝过期计划。
- 支持批量低风险治理、失败队列、failed/interrupted 重试和 superseded 审计关联。
- OpenList rename/move 禁止静默覆盖目标文件，变更后执行验证并记录操作日志。
- 支持 ignore / unignore / `ignore_until` 生命周期。
- 支持 Range metrics 和 preview-first、限量执行的 Retention 清理。
- 高风险治理、恢复和 Retention 操作使用独立 FastAdmin 权限节点。

### 数据库升级

在线更新包新增并按顺序执行：

- `2026091901_phase20_ipa_foundation.sql`
- `2026091902_phase20_ipa_center.sql`
- `2026091903_phase20_ipa_scan.sql`
- `2026091904_phase20_ipa_parser.sql`
- `2026091905_phase20_ipa_binding.sql`
- `2026091906_phase20_ipa_writeback.sql`
- `2026091907_phase20_ipa_governance.sql`
- `2026091908_phase20_ipa_production.sql`
- `2026091909_phase20_ipa_lifecycle.sql`

9 组 migration 已在真实 MySQL 5.7 Actions 服务中连续执行两遍并通过关键表和权限规则校验。

### 在线更新与兼容性

- Phase 20 控制器、服务类、页面、JS、Parser 和 9 组 migration 已进入 `zonoe-online-update.zip`。
- 更新包继续使用 `release/online-update-files.txt` + `tools/build_online_update.php` 的历史构建逻辑。
- `file_sign` 继续为 `f3f6e072f814d06403ce5e393967c9e2`；本版本没有修改 `UpdateIntegrity::files()` 监控的 4 个关键文件。
- 未新增额外人工验收文件或独立正式发布机制；正式发布继续统一走 `ZONOE Source Release`。

### 验证

- Phase 20.0～20.7 contract：通过。
- OpenList HTTP list/get/rename/move、Authorization 传播、递归 IPA 发现：通过集成测试。
- updater 强制故障后的自动 rollback 与历史 rollback 回归：通过。
- Phase 20 在线更新 ZIP、SHA256、程序文件及 9 个有序 migration：通过。
- Phase 20 开发 CI Run #88：SUCCESS。
- 正式 Release 将继续执行历史 PHP 7.0 全回归、MySQL 5.7、Phase 19 HTTP load、Phase 20 integration、更新包完整性以及 `2026091809 → 2026091901` GitHub Release 在线更新 E2E。
