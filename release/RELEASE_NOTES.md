# ZONOE 软件源 2026092406

## 更新内容

本版本以 `source-v2026092405` 为升级基线，将 Dylib 验证从“是否允许运行”升级为“Dylib 身份 + 设备卡密 + App 身份 + 功能权限”的运行时授权模型，并加入 App 更新提醒、远程通知和可迁移服务器发现能力。

### 三种卡密运行时权限

- `scope=2` 仅验证卡：返回 `basic`，允许普通菜单/普通功能，不授予额外菜单和额外功能。
- `scope=3` 指定 App 卡：服务器识别当前 App 后命中授权 App 才返回 `app_plus`；若没有其他适用卡，App 不匹配时拒绝高级授权。
- `scope=1` 全软件源卡：返回 `global_plus`，不受指定 App 限制。
- 同一 UDID 多张有效卡会按当前 App 计算最高适用权限，不再只取一张卡。
- 云存档等独立付费 entitlement 继续与 `basic/app_plus/global_plus` 分离。

### App 身份识别与防 BundleID 冒充

- `scope=3` 不信任客户端声明的 `app_id`，也不只依赖可修改的 BundleID。
- 客户端 v2 上报 BundleID、`CFBundleExecutable`、主 Mach-O `LC_UUID` 和 App 版本信息。
- 服务器使用已解析、仍处于 `parsed` 状态且具有 active `ipa_asset -> ipa_category_binding -> fa_category.id` 的数据识别当前 App。
- `MachOInspector` 增加 `LC_UUID` 解析；`IpaParserService` 将主程序身份写入 `fa_ipa_app_identity`。
- 在线 `app_plus` session 与离线 grace 都绑定完整 App 身份；仅修改 BundleID 或切换 Executable/Mach-O UUID 不能继承指定 App 高级权限。

### 协议兼容

- v1 HMAC canonical 字段顺序保持不变，旧客户端继续兼容。
- v2 仅在完整 v1 canonical 后追加协议/App identity/版本字段。
- 旧响应 `ok/code/action/token/offline_grace_seconds` 保持；新增 `access_level`、`permissions`、`app_identity`、`app_update`、`notice`。
- 2405 的 `dylib_app_binding` 数据和管理接口继续保留作历史兼容/审计，但 2406 主验证链不再将它作为 App allow-list。

### 游戏更新与远程通知

- 复用 IPA 解析版本数据，比较当前运行版本与软件源最新解析版本，返回 `app_update`。
- 更新标题、正文、按钮文字和按钮动作由服务器控制，Dylib 只负责渲染。
- 新增运行时通知，可按全部 App、指定 App、最低权限、revision、priority 和时间窗口投放。

### 服务器/域名迁移容错

- 新增 `GET/POST /index/dylib_verify/config` 运行配置发现能力。
- iOS SDK 支持多个 Bootstrap URL、多个 API Endpoint、签名配置校验、Last-Known-Good、本地旧 endpoint fallback 和 offline grace。
- 后台拒绝“Bootstrap 已配置但 API Endpoint 为空”的自锁配置；Bootstrap/API 都为空时仍兼容旧 `endpointURL` 直连模式。
- 业务服务器和业务域名可通过运行配置迁移，无需因单次域名更换重新编译全部 Dylib；仍保留网络发现的物理边界。

### 后台与接入说明

- Dylib Center 调整为：注册 → 接入说明 → 权限模型 → 运行配置与通知 → 版本控制 → 验证记录。
- 主页面不再展示旧“Dylib 游戏授权（BundleID）”作为当前授权工作流，但不删除历史 binding 数据。
- 接入说明整理为客户端配置、v1/v2 HMAC、App Identity、三种权限响应、更新/通知、Bootstrap/API 切换和离线行为。

### 数据库与兼容性

- 新增 `fa_ipa_app_identity`、`fa_dylib_runtime_config`、`fa_dylib_runtime_notice`。
- 2406 migration 支持 MySQL 5.7 重复执行。
- `fa_ipa_app_identity.idx_runtime_identity` 使用 `bundle_id(64) + executable(64) + macho_uuid(36)` 索引前缀，字段本身保持完整长度，以降低旧 BaoTa/InnoDB `utf8mb4` 联合索引长度风险。
- PHP 7.0 / MySQL 5.7 兼容要求不变。

### 验证

- IPA Online Update Release Gate：真实更新 ZIP、2406 payload、PHP 7.0、MySQL 5.7 双迁移、Dylib contracts、真实 runtime MySQL 授权矩阵均已通过候选验证。
- IPA Data Center CI：PHP 7.0、MySQL 5.7、100k 数据规模、真实 iPhoneOS SDK arm64 编译通过。
- 授权矩阵覆盖：无卡 block、scope=2 basic、scope=3 命中 app_plus、scope=3 不命中、BundleID-only 冒充失败、scope=1 global_plus、多卡最高适用权限、stale identity 拒绝。

目标升级路径：`source-v2026092405 -> source-v2026092406`。
