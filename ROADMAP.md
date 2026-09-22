# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026092206-ipa-controls-regression-hotfix`
- Stable version: `2026092206`
- Release commit: `2cf0e030eb2db3a4aa079462c7a4399720214d1b`
- Release: `source-v2026092206`
- IPA Online Update Release Gate `35782863429`: SUCCESS
- Formal Release Run `35782863008`: SUCCESS
- Online-update E2E `2026092205 -> 2026092206`: SUCCESS

## 2026092206 — 已发布

- [x] 修复软件源测试按钮事件传播导致的行勾选问题。
- [x] 加固软件源保存默认值、事务和异常回滚链。
- [x] 暂停解析时识别 Parse Worker 存活状态并回收孤立 `parsing` 任务。
- [x] 清空解析结果前回收孤立任务，并保护真实运行中的解析任务。
- [x] PHP 7.0 全量回归通过。
- [x] MySQL 5.7 迁移回归通过。
- [x] HTTP 并发负载门禁通过。
- [x] GitHub Release 在线更新 E2E 通过。
- [x] `source-v2026092206` 与在线更新资产已发布。

## Next Task

- [ ] 在真实生产环境从 `2026092205` 执行在线升级到 `2026092206`。
- [ ] 验证软件源新增/编辑/测试操作，确认测试按钮不再勾选行且保存失败能显示真实错误。
- [ ] 验证解析暂停/继续、Worker 离线后的孤立任务回收。
- [ ] 验证“清空全部解析结果”仅清解析/索引/比对/尝试记录，不删除 OpenList 扫描记录、软件源和 `fa_category`。
- [ ] 完成生产验证后再决定下一阶段功能，不改写 2206 历史 Release。
