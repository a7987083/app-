# ZONOE 软件源开发路线图

## 当前基线

- Repository: `a7987083/app-`
- Stable release: `source-v2026092409`
- Stable branch: `release/2026092409-dylib-center-ux-simplification`
- Stable commit: `a36b979e86d1b39da6eabf74f5f3e1ac99b3a5a8`
- Current development branch: `feature/2026092410-dylib-crud-legacy-codegen`
- Current verified development checkpoint: `69208ccfd595ccd8cb4aab7093838eb5dc7f4c55`
- Historical release/tag commits remain immutable.

## 2026092410 — Dylib CRUD / Version CRUD / Legacy API Codegen

### 已完成

- [x] 移除 Dylib“产生历史后禁止删除”的限制。
- [x] Dylib 删除改为事务级彻底删除：Dylib 本体 + `dylib_version` + 历史 `dylib_app_binding` + 对应 `dylib_verify_log`。
- [x] 版本管理增加编辑入口；复用 `saveVersion(id)` 完成更新。
- [x] 版本管理增加删除入口与 `deleteVersion()` 后端 API。
- [x] 编辑版本时保留 `file_size`、SHA256、state、offline_grace、fail_action、notice。
- [x] OC Generator 从 Dylib、版本、Bootstrap、API Endpoint、验证密钥自动生成配置。
- [x] OC Generator 2.1.0 同时导出历史 `Index::dylib()` / `Index::apiface()` 的候选 URL。
- [x] `GeneratedConfig.json` / `INTEGRATION.md` / `*DylibConfig.h/.m` 均包含 legacy API 信息。
- [x] 已确认当前 `application/index/controller/Index.php` 仍真实保留 `dylib()` 与 `apiface()`；没有重新复制旧控制器实现。
- [x] OC Codegen CI #9 / Run `36230506336`：SUCCESS。
- [x] PHP 7.0 lint/contracts、Dylib Center UX contract、online-update package gate、MySQL 5.7 migration regression：SUCCESS。

### 待做

- [ ] 在后台真机/浏览器实际操作：删除一个带版本历史的测试 Dylib，确认关联历史按预期清理。
- [ ] 实际编辑版本后刷新页面，确认字段完整保留。
- [ ] 实际删除一个版本，确认旧客户端使用该版本返回 `version_unknown`。
- [ ] 从 OC 接入代码生成 ZIP，确认 `legacyDylibURLs` / `legacyApiFaceURLs` 与当前 API Base URL 一致。
- [ ] 完成 2410 release metadata / release gate / online-update E2E 后再发布 `source-v2026092410`。

## 下一任务

先做 2410 后台实际 CRUD 验收和生成 ZIP 内容验收；没有回归后再进入正式 Release Gate。