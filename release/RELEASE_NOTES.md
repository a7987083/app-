# ZONOE 软件源 2026091802

## Phase 19.2 — Legacy `/appstore` 高性能缓存与授权中心修复

本版本以 `2026091801` 为稳定基线，客户端协议保持不变，用户继续只添加原 `/appstore` 地址。公开 AppStore V3 协议正式退役，原 `fa_source_change` / revision 保留为服务端内部缓存失效基础设施。

## 更新内容

### Legacy `/appstore` 高性能缓存

- 新增 `SourceLegacyCache`，按 Guest、全软件源授权、指定 App 授权集合隔离缓存，避免不同权限之间串用下载 URL。
- `SourceAppRepository` 增加跨 PHP-FPM worker 的 generation token；App 数据变更后旧映射缓存与旧响应缓存自动失效。
- `SourceChangeLog::currentRevision()` 增加共享 revision 缓存，保留单调 revision 作为服务端缓存版本依据，减少高频请求重复读取 MySQL。
- `AppStorePayload::apps()` 接入授权范围级映射缓存，减少大量 App 每次刷新时重复遍历和映射。
- `SourceResponse::plainBody()` 接入 Legacy 最终 JSON body 缓存，客户端字段、顺序和原协议行为保持不变。
- 原 15 秒 `fa_category` 行缓存继续保留，与 revision/generation 形成多层缓存失效机制。

### 公开 V3 退役

- 删除 `/appstore/v3/meta`、`/appstore/v3/apps`、`/appstore/v3/delta` 公网路由。
- 后台 `source_v3` 配置开关通过 `2026091802_retire_source_v3.sql` 删除。
- `fa_source_change` 不删除，继续用于服务器内部 revision/cache invalidation。
- `SourceV3.php` 在线升级后覆盖为 404 tombstone，避免旧安装通过 ThinkPHP 默认 controller 路由继续调用旧 V3 实现。
- `SourceSyncV3.php` 不再进入在线更新包。

### 授权中心修复

- “换绑记录”和“授权事件”的清空按钮恢复 FastAdmin 原生 `Layer.confirm + Backend.api.ajax` 交互，不再走普通 HTML POST。
- 清空成功后当前列表立即刷新，不再出现“页面将在 1 秒后自动跳转”。
- 清空日志后旧“授权总览”标签不再保留陈旧 DOM 数据；重新进入时重新从数据库加载。
- `public/assets/js/backend/authorization.js` 已纳入在线更新包，避免只更新 PHP、不更新前端逻辑。

### 配置缓存一致性

- 后台修改软件源相关配置后主动失效 `SourceConfigRepository` 缓存，避免配置保存成功但 `/appstore` 暂时继续读取旧配置。

### 兼容性

- 客户端无需支持 V3，无需修改添加源地址。
- `/appstore` 路由和 Legacy payload 字段保持不变。
- Guest / 全源卡 / 指定 App 卡授权边界保持隔离。
- 普通 / V2 加密入口保持兼容；本版本主要优化 Legacy 映射和明文最终响应热路径。
- PHP 7.0、MySQL 5.7 继续作为发布兼容基线。

### 验证

- Phase 19.2 feature CI Run `35275598054` 已通过。
- PHP 7.0：授权中心回归、Legacy 缓存契约、V3 退役契约、在线更新 ZIP 内容检查通过。
- 在线更新包确认包含 `SourceLegacyCache.php`、授权中心 JS、V3 退役 SQL，并确认不包含 `SourceSyncV3.php`。
- 正式 Release 流水线将执行完整 PHP 7.0 回归、MySQL 5.7 迁移、ZIP/SHA256 生成，并执行 `2026091801 -> 2026091802` GitHub Release 在线更新 E2E。

## 在线更新

- 正式版本：`2026091802`
- 基线：`2026091801`
- GitHub Release：`source-v2026091802`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
