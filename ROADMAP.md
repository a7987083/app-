# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092405`
- Current stable release: `source-v2026092406`
- Release branch: `release/2026092406-dylib-global-app-support`
- Release commit: `798cbed23aedfefa2fd34a280ae70a01bb413e63`
- Draft PR: `#25`（仍未 merge；正式 Release 直接从 release branch 发布）
- Historical releases through 2026092405 remain immutable.

## 2026092406 — Dylib runtime authorization / App identity / infrastructure migration

### 已完成

- [x] 将 Dylib 有效性与用户功能权限拆层；Dylib 不再使用自身 BundleID 白名单决定是否可运行。
- [x] 三种卡密进入真实运行时权限模型：`scope=2 -> basic`、`scope=3 + App命中 -> app_plus`、`scope=1 -> global_plus`；同一 UDID 取当前 App 下最高适用权限。
- [x] scope=3 不信任客户端声明的 `app_id`，也不只依赖 BundleID；使用 BundleID + Executable + Mach-O `LC_UUID`，再由服务器通过 active `ipa_asset -> ipa_category_binding -> fa_category.id` 识别 App。
- [x] `MachOInspector` 增加 `LC_UUID` 解析；`IpaParserService` 将主程序身份写入 `fa_ipa_app_identity`。
- [x] 只有 `parsed` 且存在 active IPA→App 绑定的解析结果可参与指定 App 高级授权。
- [x] 2405 `dylib_app_binding` 数据和接口保留作历史兼容/审计，但 2406 主验证链不再读取它。
- [x] v1 HMAC canonical 顺序保持不变；v2 仅在完整 v1 原文后追加 protocol/App identity/version 字段。
- [x] 验证响应保留旧 `ok/code/action/token/offline_grace_seconds`，新增 `access_level`、`permissions`、`app_identity`、`app_update`、`notice`。
- [x] session token v2 绑定服务器识别的 App ID、access level 和 Mach-O UUID。
- [x] `app_plus` 离线 grace 再次核对 BundleID + Executable + Mach-O UUID；身份不匹配时清缓存并拒绝离线高级权限。
- [x] 复用 IPA 解析结果判断当前 App 是否有新版本；更新标题、正文、按钮文字和动作由服务器配置。
- [x] 增加远程通知：全部 App / 指定 App / 最低权限 / revision / priority / 时间窗口 / 按钮动作。
- [x] 增加 `/index/dylib_verify/config`；支持多个 Bootstrap、多个 API endpoint、签名配置、Last-Known-Good、旧 endpoint fallback、offline grace。
- [x] 后台拒绝 Bootstrap 非空但 API Endpoint 为空的自锁配置；Bootstrap/API 都空时保留 legacy endpoint 模式。
- [x] Dylib Center 整理为：注册 → 接入说明 → 权限模型 → 运行配置与通知 → 版本控制 → 验证记录。
- [x] 新增 MySQL 5.7 可重复执行 migration 和 clean-install schema；`idx_runtime_identity` 使用 `64/64/36` 前缀索引。
- [x] 新增真实 ThinkPHP Db + PHP 7.0 + MySQL 5.7 授权矩阵测试。

### 自动验证证据

- [x] Final pre-release code checkpoint `38405039baf83de0e1bcb5ae2db4f4645c349bed`。
- [x] Pre-release IPA Online Update Release Gate `36097524318` — SUCCESS。
- [x] Pre-release IPA Data Center CI `36097528583` — SUCCESS；PHP 7.0 / MySQL 5.7 / 100k / iPhoneOS SDK arm64 全部通过。
- [x] Acceptance Package Run `36100165394` — SUCCESS；Artifact ID `10849162793`。
- [x] Formal release metadata atomic commit `798cbed23aedfefa2fd34a280ae70a01bb413e63`。
- [x] Final formal IPA Online Update Release Gate `36109410680` — SUCCESS；正式版本 metadata、真实 online-update ZIP、2406 payload、PHP 7.0、MySQL 5.7 双迁移、runtime contracts 均通过。
- [x] ZONOE Source Release `36109410642` — SUCCESS。
- [x] GitHub Release `source-v2026092406` 已发布；Release ID `396411187`，target commit `798cbed23aedfefa2fd34a280ae70a01bb413e63`，`draft=false`，`prerelease=false`。
- [x] Release asset `zonoe-online-update.zip`：221101 bytes，asset ID `587832059`，GitHub digest `sha256:2d7affcc75ed7a33c1ec2d1c0ad39f1ed0e765ee30b2a4c6ccfc8cc7eb5339c5`。
- [x] Release asset `zonoe-online-update.zip.sha256`：asset ID `587832060`。
- [x] CI release artifact `zonoe-source-2026092406-online-update`：ID `10852219528`，digest `sha256:7788e599a6b5cb33d69a204535bb698b181257c34f4d3491784a44af7092ca2b`。
- [x] **Real GitHub Release online-update E2E (`2026092405 -> 2026092406`) — SUCCESS**：`self_update=passed progress=passed history=passed db_migration=yes`。

### 仍需真实环境验收

- [ ] 在用户真实 BaoTa 软件源中点击“GitHub 在线更新”，确认 2405 → 2406 成功并记录更新历史/备份。
- [ ] 确认真实 BaoTa MySQL 上 2406 migration、三张新表和 `64/64/36` 索引正常。
- [ ] 重新解析至少一个 active 绑定 App，确认生成 `fa_ipa_app_identity`。
- [ ] 真机验证 `scope=2`、`scope=3`、`scope=1` 与同 UDID 多卡权限合并。
- [ ] 真机验证另一个游戏仅修改 BundleID 不能获得 `scope=3 app_plus`。
- [ ] 真机验证 `app_plus` 离线缓存不能跨 Executable/Mach-O UUID 复用。
- [ ] 真机验证游戏更新弹窗、服务器自定义通知与按钮 URL。
- [ ] 真机演练 API 域名切换、Bootstrap failover、Last-Known-Good、offline grace。

## 2026092405 — 历史稳定发布

- Release: `source-v2026092405`
- Release commit: `f2cb8536b2a5196b4dab1c135c74033f740ed398`
- Source Release `36021185414` — SUCCESS。
- Final Gate `36021185353` — SUCCESS。
- Real GitHub Release online-update E2E (`2404 -> 2405`) — SUCCESS。
- 2405 历史 Release 保持不可改写。

## Next Task

在真实软件源后台使用“GitHub 在线更新”从 `2026092405` 升级到已经正式发布的 `2026092406`。升级成功后立即核对版本号、更新历史/备份、三张 2406 数据表，并重新解析一个已绑定 App；随后进入三卡权限、App 身份防伪、通知/更新、Bootstrap/API 迁移与离线容错真机验收。
