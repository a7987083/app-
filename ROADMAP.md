# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026092206-ipa-controls-regression-hotfix`
- Stable version: `2026092206`
- Stable release: `source-v2026092206`
- 2207 development branch: `release/2026092207-ipa-controls-regression-fix`
- 2207 baseline: `cd719391f25713224dab4a1301f9e551b0cde969`
- 2207 code HEAD: `51518a17b7338670d63ab68b925caa82c9010904`
- Validation PR: `#22` (draft, base = 2206 hotfix branch)

## 2026092207 — IPA controls regression fix

- [x] 全量扫描恢复“重新扫描”语义：同源旧 pending/running 扫描先取消，再创建新的 full job；增量扫描仍保持互斥。
- [x] 移除每 N 分钟/每小时/每天的解析数量限额执行逻辑；保留显式自动解析开关。
- [x] 移除 IPA Center 新增的 Worker 状态和限额用量 UI。
- [x] 修复 `pauseParse` / `resumeParse` / `saveParseSettings` / `startScan` 捕获框架正常 `HttpResponseException` 的响应链。
- [x] 清空解析继续保留 IPA 扫描/发现记录与 `fa_category`，仅清解析派生数据。
- [x] `IPA Data Center CI` Run `35919441555` — SUCCESS。
- [x] `Regression Checks` Run `35919441640` — SUCCESS。
- [x] `Phase14 Production Hardening` Run `35919441698` — SUCCESS。
- [ ] 真实 BaoTa/后台页面手工验证。
- [ ] 验证 full scan 在正在扫描时点击后旧 job 变 cancelled、新 full job 正常完成。
- [ ] 验证暂停/继续页面不再出现 `think\exception\HttpResponseException`。
- [ ] 验证大量 IPA 自动解析不再被 5 分钟/小时/每日配额阻断。
- [ ] 验证清空解析后 IPA discovery 记录和 `fa_category` 不变。

## Next Task

在真实测试部署上执行上述 4 项 UI/运行时验证；通过后再决定是否打 `2026092207` 正式更新包。不要改写 `source-v2026092206` 历史 Release。
