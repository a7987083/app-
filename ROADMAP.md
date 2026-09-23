# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026092206-ipa-controls-regression-hotfix`
- Previous stable release: `source-v2026092206`
- Current 2207 branch: `release/2026092207-ipa-controls-regression-fix`
- 2207 release commit: `49372574305bbf2e52b6b1965e4151a142de8a8a`
- 2207 release: `source-v2026092207`
- Validation PR: `#22` (draft, base = 2206 hotfix branch)

## 2026092207 — IPA controls regression fix

- [x] 全量扫描恢复“重新扫描”语义：同源旧 pending/running 扫描先取消，再创建新的 full job；增量扫描仍保持互斥。
- [x] 移除每 N 分钟/每小时/每天的解析数量限额执行逻辑；保留显式自动解析开关。
- [x] 移除 IPA Center 新增的 Worker 状态和限额用量 UI。
- [x] 修复 `pauseParse` / `resumeParse` / `saveParseSettings` / `startScan` 捕获框架正常 `HttpResponseException` 的响应链。
- [x] 清空解析继续保留 IPA 扫描/发现记录与 `fa_category`，仅清解析派生数据。
- [x] 2207 Online Update Release Gate Run `35929007415` — SUCCESS。
- [x] ZONOE Source Release Run `35930081424` — SUCCESS。
- [x] `source-v2026092207` 已发布，目标升级路径 `source-v2026092206 -> source-v2026092207`。
- [x] CI Artifact `zonoe-source-2026092207-online-update` 已生成。
- [x] GitHub Release 在线升级 E2E — SUCCESS。
- [ ] 真实 BaoTa/后台页面手工验证。
- [ ] 验证 full scan 在正在扫描时点击后旧 job 变 cancelled、新 full job 正常完成。
- [ ] 验证暂停/继续页面不再出现 `think\exception\HttpResponseException`。
- [ ] 验证大量 IPA 自动解析不再被 5 分钟/小时/每日配额阻断。
- [ ] 验证清空解析后 IPA discovery 记录和 `fa_category` 不变。

## Next Task

2207 已具备正式在线更新产物和 CI/E2E 证据。下一步在真实 BaoTa/测试环境执行 2206 -> 2207 在线更新，并完成上述 4 项 UI/运行时验证。不要改写 `source-v2026092206` 历史 Release。
