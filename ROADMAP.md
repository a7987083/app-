# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092404`
- Stable release branch: `release/2026092405-dylib-lifecycle-integration`
- Stable release commit: `f2cb8536b2a5196b4dab1c135c74033f740ed398`
- Current stable release: `source-v2026092405`
- Current development branch: `release/2026092406-dylib-global-app-support`
- 2406 runtime code checkpoint: `b5c3c00bf774583aed0879c6524d3ece1f1151e0`
- 2406 verification-hardening checkpoint: `f713e449e3a2a6ec0a0aa4405984063093cb23b1`
- Draft PR: `#25`
- 2406 has NOT been tagged/released yet. Historical releases through 2026092405 must not be rewritten.

## 2026092406 — Dylib runtime authorization / App identity / infrastructure migration

- [x] 将 Dylib 是否有效与用户功能权限拆层；Dylib 不再使用自身 BundleID 白名单决定是否可运行。
- [x] 三种卡密进入真实运行时权限模型：`scope=2 -> basic`、`scope=3 + App命中 -> app_plus`、`scope=1 -> global_plus`，同一 UDID 取当前 App 下最高适用权限。
- [x] scope=3 不信任客户端声明的 `app_id`，也不只依赖 BundleID；使用 BundleID + Executable + Mach-O `LC_UUID`，再由服务器通过 active `ipa_asset -> ipa_category_binding -> fa_category.id` 识别 App。
- [x] `MachOInspector` 增加 `LC_UUID` 解析；`IpaParserService` 将主程序身份写入 `fa_ipa_app_identity`。
- [x] 只有仍处于 `parsed` 状态且存在 active IPA→App 绑定的解析结果可参与指定 App 高级授权。
- [x] 2405 `dylib_app_binding` 数据和接口保留作历史兼容/审计，但 2406 主验证链不再读取它。
- [x] v1 HMAC 原文顺序保持不变；v2 仅在完整 v1 原文后追加 protocol/App identity/version 字段。
- [x] 验证响应保留旧 `ok/code/action/token/offline_grace_seconds`，新增 `access_level`、`permissions`、`app_identity`、`app_update`、`notice`。
- [x] session token v2 绑定服务器识别的 App ID、access level 和 Mach-O UUID，避免 scope=3 高级 session 跨 App 复用。
- [x] 复用 IPA 解析结果判断当前 App 是否存在新版本；更新标题、正文、按钮文字由服务器配置。
- [x] 增加按全部 App/指定 App/最低权限投放的远程通知，支持 notice key、revision、priority、时间窗口和按钮动作。
- [x] 增加 `/index/dylib_verify/config` 运行配置发现；支持多个 Bootstrap、多个 API endpoint、HMAC 验签 Last-Known-Good、旧 endpoint fallback、离线授权 fallback。
- [x] 后台页面整理为：注册 → 接入说明 → 权限模型 → 运行配置与通知 → 版本控制 → 验证记录。
- [x] 接入说明重写为可直接照做的客户端配置、v1/v2 HMAC、权限响应、服务器迁移与离线行为说明。
- [x] 新增 MySQL 5.7 可重复执行迁移和 clean-install schema；在线更新包包含 2406 runtime 文件和 SQL。
- [x] Draft PR #25 已建立，base 为 2405 开发分支；未创建 2406 tag/release。
- [x] IPA Online Update Release Gate `36088476047` — SUCCESS；包含 2406 ZIP payload、PHP 7.0、新 SQL MySQL 5.7 双执行和 contract。
- [x] IPA Data Center CI `36088568733` — SUCCESS；contracts / PHP 7.0 / iPhoneOS SDK arm64 真编译全部成功。
- [x] Regression Checks `36088568690` — SUCCESS。
- [x] Phase14 Production Hardening `36088568743` — SUCCESS。
- [x] Phase 17.2 Authorization Integrity `36088568735` — SUCCESS。
- [x] 新增 `tests/dylib_runtime_access_mysql_test.php`，在真实 ThinkPHP Db + PHP 7.0 + MySQL 5.7 下直接调用 `DylibRuntimeAccessService`。
- [x] IPA Data Center CI `36095035822` — SUCCESS；真实数据库授权矩阵覆盖无卡、scope=2、scope=3 命中/不命中、只改 BundleID 冒充失败、scope=1、同 UDID 多卡合并、解析状态 stale 失效。
- [x] 同一轮 `36095035822` 的 contracts / PHP 7.0 / MySQL 5.7 / 100k / iPhoneOS arm64 全部 SUCCESS。
- [ ] 真实 BaoTa 执行 2406 migration / 在线更新验证。
- [ ] 真机分别验证 `scope=2`、`scope=3`、`scope=1` 以及同 UDID 多卡权限合并。
- [ ] 真机验证“另一个游戏仅修改 BundleID”不能获得 scope=3 `app_plus`。
- [ ] 真机验证服务器更新弹窗/自定义通知及按钮 URL。
- [ ] 真机演练 API 域名切换、Bootstrap 故障转移、Last-Known-Good 和离线 grace。

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

在测试/BaoTa 环境部署 2406 migration，并重新解析至少一个已绑定 App 生成 `fa_ipa_app_identity`；随后用真实 Dylib/UDID 逐项验证三种卡密权限、scope=3 App 身份防伪、游戏更新/远程通知，以及 Bootstrap/API 域名切换与离线容错。所有真机结果通过前不创建 `source-v2026092406` 正式发布。
