# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092404`
- Current release branch: `release/2026092405-dylib-lifecycle-integration`
- Release commit: `f2cb8536b2a5196b4dab1c135c74033f740ed398`
- Current release: `source-v2026092405`
- Historical releases through 2026092404 must not be rewritten.

## 2026092405 — Dylib lifecycle / integration workflow

- [x] 已注册 Dylib 增加编辑、停用、启用、删除和接入说明。
- [x] 编辑态锁定 Dylib Key；验证密钥允许显式轮换，留空不改变。
- [x] 验证链确认只接受 `enabled=1`；停用保留配置和历史并继续按既有 `dylib_unknown / block` 拒绝。
- [x] 删除策略按真实引用关系收口：未使用项可硬删；存在版本、BundleID 授权或验证日志时禁止删除并要求停用。
- [x] 页面调整为 注册 → 接入 → 游戏授权 → 版本控制 → 验证记录。
- [x] 状态、动作和 result_code 仅在 UI 中文映射，数据库/协议枚举不变。
- [x] 接入说明取自真实 `/index/dylib_verify/verify`、DylibVerificationService 和 ZONVerifyClient；未新增 endpoint。
- [x] 日志列改为管理员可读显示，UDID 继续只显示哈希摘要。
- [x] 删除二次确认、Backend 权限与 Fast.api.ajax/CSRF 链保持现状。
- [x] PR #24 pre-release IPA Data Center CI `36020461657` — SUCCESS。
- [x] PR #24 Regression Checks `36020461082` — SUCCESS。
- [x] PR #24 Phase14 Production Hardening `36020461401` — SUCCESS。
- [x] PHP 7.0 / MySQL 5.7 / Dylib signing & lifecycle contract / iPhoneOS arm64 compile — SUCCESS。
- [x] ZONOE Source Release `36021185414` — SUCCESS。
- [x] GitHub Release `source-v2026092405` 已发布，目标 Commit `f2cb8536...`。
- [x] Real GitHub Release online-update E2E (`2404 -> 2405`) — SUCCESS。
- [x] Final IPA Online Update Release Gate `36021185353` — SUCCESS。
- [x] CI Artifact `zonoe-source-2026092405-online-update` ID `10816991706` 已生成。
- [ ] 真实 BaoTa UI 验证编辑、启停、引用保护删除、接入说明和验证日志展示。

## 保留验证项

- [ ] 2404 的真实 BaoTa IPA 扫描/自动解析/软件源保存测试仍需生产验证。
- [ ] 大型 OpenList 树与批量解析时 PHP-FPM worker 占用情况仍需生产观察。

## Next Task

在真实 BaoTa 的 `source-v2026092404` 环境通过现有在线更新入口升级到 `source-v2026092405`，随后逐项验证 Dylib 编辑、停用/启用、删除保护、接入说明及验证记录中文显示，并确认旧客户端协议、版本规则和 BundleID 绑定行为保持不变。
