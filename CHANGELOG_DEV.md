# Development Changelog

## 2026-09-25 — Release 2026092406

Baseline: `source-v2026092405`; release branch `release/2026092406-dylib-global-app-support`; formal release commit `798cbed23aedfefa2fd34a280ae70a01bb413e63`.

### Dylib runtime authorization

- 将“Dylib 是否有效”和“用户能使用哪些菜单/功能”拆成两层。
- 2406 主验证链不再使用 `dylib_app_binding` 作为运行 App 白名单；旧表和旧后台接口继续保留用于历史兼容/审计。
- 三种卡密进入运行时权限模型：`scope=2 -> basic`、`scope=3 + 当前 App 身份命中 -> app_plus`、`scope=1 -> global_plus`。
- 同一 UDID 多张有效卡按当前 App 返回最高适用权限。
- 旧响应字段保持兼容，新增 `access_level`、`permissions`、`app_identity`、`app_update`、`notice`。

### App identity / IPA parser

- `MachOInspector` 增加 Mach-O `LC_UUID` 解析。
- `IpaParserService` 将 BundleID、Executable、Mach-O UUID 写入 `fa_ipa_app_identity`。
- scope=3 不信任客户端声明 `app_id`，也不只相信 BundleID；服务器通过 parsed identity + active `ipa_asset -> ipa_category_binding -> fa_category.id` 识别 App。
- 只有 `parsed` 且 active 绑定的解析记录参与 App-specific authorization。
- v1 HMAC canonical 保持；v2 追加协议/App identity/版本字段。
- `app_plus` 在线 session 和 offline grace 均绑定完整 App identity；离线恢复再次核对 BundleID + Executable + Mach-O UUID。

### App update / notice / infrastructure migration

- IPA 解析版本数据用于游戏更新检测。
- 更新标题、正文、按钮文字和动作由服务器下发。
- 增加可按 App / access / 时间投放的 runtime notice。
- 新增 `/index/dylib_verify/config`；iOS SDK 支持多 Bootstrap、多 API endpoint、签名配置、Last-Known-Good、legacy endpoint 和 offline fallback。
- Bootstrap 非空但 API Endpoint 为空的配置会被后台拒绝；两者都空仍保留 legacy 模式。

### Admin / integration documentation

- Dylib Center：注册 → 接入说明 → 权限模型 → 运行配置与通知 → 版本控制 → 验证记录。
- 旧 Dylib BundleID 授权不再作为主工作流展示，但历史数据保留。
- 接入说明覆盖 v1/v2 HMAC、App Identity、权限响应、更新/通知、服务器迁移和离线行为。

### Database / compatibility

- 新增 `fa_ipa_app_identity`、`fa_dylib_runtime_config`、`fa_dylib_runtime_notice`。
- MySQL 5.7 migration 可重复执行。
- `idx_runtime_identity` 使用 `bundle_id(64) + executable(64) + macho_uuid(36)` 前缀以提高旧 InnoDB/utf8mb4 兼容性。
- PHP 7.0 / MySQL 5.7 兼容要求不变。

### Verification and formal online release

- Pre-release code checkpoint: `38405039baf83de0e1bcb5ae2db4f4645c349bed`。
- Pre-release Final Gate `36097524318` — SUCCESS。
- Pre-release IPA Data Center CI `36097528583` — SUCCESS；PHP 7.0 / MySQL 5.7 / 100k / iPhoneOS arm64 全绿。
- Acceptance Package Run `36100165394` — SUCCESS；Artifact ID `10849162793`。
- Formal release metadata committed atomically as `798cbed23aedfefa2fd34a280ae70a01bb413e63`。
- Formal IPA Online Update Release Gate `36109410680` — SUCCESS。
- ZONOE Source Release `36109410642` — SUCCESS。
- GitHub Release `source-v2026092406` published successfully, Release ID `396411187`, target `798cbed23aedfefa2fd34a280ae70a01bb413e63`, non-draft/non-prerelease。
- Release `zonoe-online-update.zip`: 221101 bytes, asset ID `587832059`, GitHub digest `sha256:2d7affcc75ed7a33c1ec2d1c0ad39f1ed0e765ee30b2a4c6ccfc8cc7eb5339c5`。
- Release `zonoe-online-update.zip.sha256`: asset ID `587832060`。
- CI copy `zonoe-source-2026092406-online-update`: artifact ID `10852219528`, digest `sha256:7788e599a6b5cb33d69a204535bb698b181257c34f4d3491784a44af7092ca2b`。
- **Real GitHub Release online-update E2E (`2026092405 -> 2026092406`) — SUCCESS**。
- Exact E2E result: `self_update=passed progress=passed history=passed db_migration=yes`。

### Runtime boundary after release

- 在线发布链已验证，2405 软件源的“GitHub 在线更新”应能发现并安装 2406。
- 用户真实 BaoTa 的一次实际点击更新仍需要现场确认；CI/E2E 不替代用户服务器环境的最终运行结果。
- 真机三卡权限、BundleID-only spoof、offline app_plus identity、更新通知、Bootstrap/API failover 仍待真实设备验收。

## 2026-09-24 — Release 2026092405

- Release commit `f2cb8536b2a5196b4dab1c135c74033f740ed398`。
- Final Gate `36021185353` — SUCCESS。
- ZONOE Source Release `36021185414` — SUCCESS。
- Real GitHub Release online-update E2E (`source-v2026092404 -> source-v2026092405`) — SUCCESS。
- Historical release remains immutable.
