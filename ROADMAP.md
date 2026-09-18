# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026091804-phase19-3-1-concurrency-api-center`
- Stable phase/version: `Phase 19.3.1 / 2026091804`
- Stable commit: `34fc713346eafe92f23d1f5fcf470526daf8ddb6`
- Release: `source-v2026091804`
- Phase 19.3.1 gate: Run `35291515475` — SUCCESS
- ZONOE Source Release Run `35291515730`: package/release SUCCESS, final online-update E2E FAILURE only because Release Notes lacked literal `更新内容`.
- `main` 不是活动开发/发布基线。

## Phase 19.4 — Dynamic Announcement + License Routing

Development branch: `feature/2026091805-phase19-4-dynamic-announcement-license`

### 已完成

- [x] 新增 `SourceAnnouncementTemplate` 动态公告渲染层。
- [x] 支持刷新时间、软件数量、今日/七日更新、授权状态、全源/验证到期、指定 App 数、授权摘要、源名称、服务器时间。
- [x] 支持默认值语法，例如 `[全源到期时间|未解锁]`。
- [x] Guest / 全源 / 仅验证 / 指定 App 授权数据独立计算。
- [x] 动态公告在共享缓存之后、JSON/加密之前注入，避免 UDID 维度污染公共缓存。
- [x] Legacy plain/encrypted JSON cache 采用 sentinel 静态缓存，并将 body/encrypted-json cache key 升为 v2。
- [x] 后台公告板增加变量快捷插入、可选 UDID 实时预览。
- [x] `/LICENSE` exact 404 与 `/license` exact ThinkPHP 路由分离。
- [x] online-update manifest 补齐 `license.html`、`unbind.html`、`AuthorizationLicense.php`、动态公告运行时和 `nginx.rewrite`。
- [x] Phase 19.4 PHP 7.0 / Legacy / deployment / update-package gate：Run `35296525450` SUCCESS。

### 发布前剩余

- [ ] 在真实 BaoTa/Nginx 将新版 rewrite 应用到活动站点配置并 reload。
- [ ] 验证 `/LICENSE -> 404`、`/license GET -> 200`、`/license POST` 卡密+UDID 查询。
- [ ] 实机验证静态公告与动态公告在明文/普通加密/V2 下均正确。
- [ ] 实机验证 Guest / 全源卡 / 指定 App 卡 / 仅验证卡公告隔离。
- [ ] 回归 API Center 日志刷新、测试下拉、API 开关真实 503/恢复。
- [ ] 将 VERSION / ver.txt / ver.json / Release Notes 收口为 `2026091805` 后跑正式 Release 全门禁和在线更新 E2E。

## 保持不变的稳定边界

- 不改变旧 `appstore / appstore_v2` 公共字段、URL 和加密 envelope。
- 不改变卡密一次性消费、授权时长叠加及 `transfer_count` 剩余换绑次数语义。
- 不恢复 `App-mb.php` / `Index2.php`。
- 不无计划迁移 `fa_category` 物理字段。
- 远程 `dylib()` 内部授权逻辑继续延期，除非另开独立阶段。
