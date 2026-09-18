# ZONOE 软件源 2026091805

## 更新内容

Phase 19.4 聚焦“动态软件源公告”和“授权查询路由修复”。本说明已提前准备在开发分支；正式 Release 前仍需完成真实 BaoTa/Nginx 与 iOS 真机回归。

### 动态公告中心

软件源公告板支持以下动态变量：

- `[刷新时间]`、`[服务器时间]`、`[源名称]`
- `[软件个数]`、`[今日更新]`、`[七日更新]`
- `[授权状态]`、`[全源到期时间]`、`[全源剩余时间]`
- `[验证到期时间]`、`[指定APP数量]`、`[授权摘要]`

支持默认值，例如 `[全源到期时间|未解锁]`。

动态值在共享缓存读取后、JSON 加密前注入。Guest、全源、仅验证和指定 App 授权不会把用户动态公告写进公共缓存。

后台公告配置增加变量快捷插入和可选 UDID 实时预览。

### /license 与在线更新

- 保留真实 `/LICENSE` 404 防护。
- 精确 `/license` 进入 ThinkPHP，避免被大小写不敏感 LICENSE 安全规则误拦。
- 在线更新包补齐 `license.html`、`unbind.html`、`AuthorizationLicense.php`、动态公告运行时和 `nginx.rewrite`。

### 兼容性

- `/appstore` URL 不变。
- Legacy AppStore 公共字段不变。
- 普通加密与 V2 envelope 不变。
- 卡密一次性激活、授权叠加、Guest/全源/指定 App/仅验证权限边界不变。
- PHP 7.0 / MySQL 5.7 继续作为发布兼容基线。

### 当前验证状态

Phase 19.4 专项 CI Run `35296525450` 已通过 PHP 7.0 lint、动态公告、Legacy cache、SourceResponse、API Center、deployment、card scope 和在线更新包覆盖测试。

正式发布前仍需完成真实 BaoTa Nginx reload、`/license` GET/POST 以及 iOS 明文/普通加密/V2 真机回归。
