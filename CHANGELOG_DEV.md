# Development Changelog

## 2026-09-25 — Development 2026092406（未发布）

Baseline: `source-v2026092405`; development branch `release/2026092406-dylib-global-app-support`; Draft PR `#25`.

### Dylib runtime authorization

- 将“Dylib 是否有效”和“用户能使用哪些菜单/功能”拆成两层。
- 2406 主验证链不再使用 `dylib_app_binding` 作为运行 App 白名单；旧表和旧后台接口继续保留用于历史兼容/审计。
- 三种卡密进入运行时权限模型：`scope=2 -> basic`、`scope=3 + 当前 App 身份命中 -> app_plus`、`scope=1 -> global_plus`。
- 同一 UDID 存在多张有效卡时，服务端按当前 App 计算并返回最高适用权限，而不是只取一张卡。
- 旧响应 `ok/code/action/token/offline_grace_seconds` 保持兼容，新增 `access_level`、`permissions`、`app_identity`、`app_update`、`notice`。
- session token v2 绑定 App Identity、access level 和 Mach-O UUID，降低 scope=3 高级权限跨 App 复用风险。

### App identity / IPA parser

- `MachOInspector` 增加 Mach-O `LC_UUID` 解析。
- `IpaParserService` 将主程序 BundleID、Executable、Mach-O UUID 等身份写入 `fa_ipa_app_identity`。
- scope=3 不接受客户端自行声明的 `app_id`，也不只相信 BundleID；服务器通过解析身份和 active `ipa_asset -> ipa_category_binding -> fa_category.id` 映射识别当前 App。
- 只有 `parsed` 状态且存在 active IPA→App 绑定的解析记录才能参与 scope=3 高级权限匹配。
- v1 HMAC canonical 顺序保持不变；v2 在完整 v1 canonical 后追加 protocol/App identity/版本字段。

### App update / notice

- 复用 IPA 解析版本数据判断当前 App 是否存在新版本。
- 更新弹窗标题、正文、按钮文字和按钮动作由服务器下发，不写死在 Dylib。
- 增加远程通知能力，可按全部 App、指定 App、最低权限、revision、priority 和时间窗口投放。
- 菜单授权与独立 entitlement 继续分离；更新通知也不参与卡密权限提升。

### Infrastructure migration

- 新增 `/index/dylib_verify/config` 运行配置发现接口。
- iOS SDK 支持多个 Bootstrap、多 API endpoint、签名配置校验、Last-Known-Good、本地旧 endpoint fallback 和离线授权 fallback。
- 业务 API 域名不再作为唯一固定入口；服务器/域名迁移可通过运行配置完成。
- 保留物理边界：如果所有 Bootstrap、旧 endpoint 和本地有效缓存同时失效，旧客户端无法凭空发现未来服务器。

### Pre-release hardening

- `app_plus` 离线 grace 不再只按 BundleID 复用缓存：离线恢复时重新比对当前 BundleID、`CFBundleExecutable` 和主 Mach-O UUID；不匹配即清除缓存并拒绝高级权限。`basic/global_plus` 的跨 App 语义保持不变。
- `fa_ipa_app_identity.idx_runtime_identity` 从较长的 `utf8mb4` 联合前缀收敛为 `bundle_id(64) + executable(64) + macho_uuid(36)`；字段本身长度不变，降低旧 BaoTa/InnoDB 索引长度限制风险。
- 后台运行配置增加防自锁校验：Bootstrap URL 非空时至少需要一个 API Endpoint；Bootstrap/API 同时为空仍允许，用于 2405/legacy `endpointURL` 兼容。
- 对上述三项均增加 contract 保护，防止后续回退。

### Admin / integration documentation

- Dylib Center 主页面调整为：注册 → 接入说明 → 权限模型 → 运行配置与通知 → 版本控制 → 验证记录。
- 移除主页面上的旧“游戏授权（BundleID）”工作流展示，但不删除历史 binding 数据。
- 接入说明重写为客户端配置、v1/v2 HMAC、权限响应、App Identity、更新/通知、Bootstrap/API 切换和离线行为的可执行说明。

### Verification

Runtime code checkpoint: `b5c3c00bf774583aed0879c6524d3ece1f1151e0`.
Verification-hardening checkpoint: `f713e449e3a2a6ec0a0aa4405984063093cb23b1`.
Final pre-release code checkpoint: `38405039baf83de0e1bcb5ae2db4f4645c349bed`.

- 最终 IPA Online Update Release Gate `36097524318` — SUCCESS：真实 online-update ZIP 构建和 payload 校验通过；PHP 7.0；2406 migration 在 MySQL 5.7 连续执行两次；2406 contract 与真实 runtime MySQL 行为测试通过。
- IPA Data Center CI `36097528583` — SUCCESS：contracts / PHP 7.0 / MySQL 5.7 / 100k / iPhoneOS SDK arm64 真编译全部通过。
- 真实数据库授权矩阵覆盖：无卡 block、scope=2 basic、scope=3 命中 app_plus、scope=3 不命中 block、仅改 BundleID 冒充失败、scope=1 global_plus、同 UDID 多卡按当前 App 取最高适用权限、解析状态离开 `parsed` 后身份 stale。
- Regression Checks `36097528593` — SUCCESS。
- Phase14 Production Hardening `36097528557` — SUCCESS。
- Phase 17.2 Authorization Integrity `36097528546` — SUCCESS。
- 新增 `.github/workflows/dylib-2406-acceptance-package.yml`：只构建/上传验收包，不创建 tag、GitHub Release，也不修改正式 `VERSION/ver.json/ver.txt`。
- Dylib 2406 Acceptance Package Run `36100165394` — SUCCESS；Artifact `zonoe-2406-acceptance-36100165394` / ID `10849162793`；artifact digest `sha256:c4245424062f5a893bd6791dee119b8e72a7341a43f608ecec5d94a743cee20f`。
- 验收包内层 `zonoe-online-update.zip` 已再次本地校验，SHA256 `ae2e6b34ce5675b76afafe8f96711d81d866cf66770ffefc0c393fecf0ad3bf0`，并确认 2406 Dylib runtime services、DylibVerify、后台 UI/JS 与 `2026092406_dylib_runtime_access.sql` 均在 payload 中。

### Release boundary

- `source-v2026092405` 仍是当前正式稳定版。
- Acceptance artifact 明确标记 `ACCEPTANCE ONLY - NOT A FORMAL RELEASE`；其中 `stable_version=2026092405`，目标验收版本为 2026092406。
- 2406 尚未创建 tag/release，也尚未在真实 BaoTa/真机完成三种卡密、scope=3 App 防伪、scope=3 离线缓存身份绑定、更新通知和服务器迁移容错验收。
- 真机/生产验收完成前，不将 2406 记录为正式发布。

## 2026-09-24 — Release 2026092405

Baseline: `source-v2026092404`; release commit `f2cb8536b2a5196b4dab1c135c74033f740ed398`.

### Dylib lifecycle

- `DylibCenter` 增加 `setDylibEnabled()` 与 `deleteDylib()`；停用只修改 `enabled` 并保留历史。
- 删除先检查 `dylib_version`、`dylib_app_binding` 和 `dylib_verify_log`；有任一历史引用即拒绝硬删并要求停用。
- 编辑时 `dylib_key` 不可修改，避免破坏旧客户端查找/HMAC 契约；验证密钥留空表示不轮换。
- 页面重排为注册 → 接入 → 游戏授权 → 版本控制 → 验证记录。
- 接入说明来自真实 `/index/dylib_verify/verify` 与 `ZONVerifyClient`，未新增接口。
- 状态、动作和 result_code 仅做 UI 中文映射；底层枚举、版本规则和 BundleID 行为不变。
- 删除使用既有 `Layer.confirm` + `Fast.api.ajax`；Backend 权限/CSRF 链保持不变。

### Verification and release

- Pre-release exact-code HEAD `36e9104f6fe6b26cd7809f4d064f4016114add1f`:
  - IPA Data Center CI `36020461657` — SUCCESS。
  - Regression Checks `36020461082` — SUCCESS。
  - Phase14 Production Hardening `36020461401` — SUCCESS。
  - PHP 7.0、MySQL 5.7、Dylib signing/lifecycle/security、iPhoneOS arm64 compile 全部通过。
- Release metadata was committed atomically as `f2cb8536...` so no intermediate commit could refresh historical `source-v2026092404`.
- Final IPA Online Update Release Gate `36021185353` — SUCCESS。
- ZONOE Source Release `36021185414` — SUCCESS。
- Package/Release — SUCCESS。
- Real GitHub Release online-update E2E (`source-v2026092404 -> source-v2026092405`) — SUCCESS。
- Release `source-v2026092405` targets `f2cb8536b2a5196b4dab1c135c74033f740ed398`.
- Release ZIP `zonoe-online-update.zip`: 208933 bytes, SHA256 `abc851d4e63fd98b78eda7ce06ff9cac73b5909380efbef3511a529bc949e0f4`, asset ID `586260722`.
- CI Artifact `zonoe-source-2026092405-online-update`: ID `10816991706`, 202158 bytes, digest `sha256:91f2d63221448f9f858035c1672a17abe3082399db9b326d382a4ff7e2bb1a55`.
- Real BaoTa Dylib lifecycle/UI validation: NOT YET VERIFIED.

## 2026-09-24 — Release 2026092404

- FPM inline scan/parse, live refresh and software-source response fix released as `source-v2026092404`.
- Source Release `35964382173`, Final Gate `35964464124` and real `2403 -> 2404` GitHub Release E2E all passed.
- Historical release remains immutable.
