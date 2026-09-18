# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026091805-phase19-4-dynamic-announcement`
- Stable phase/version: `Phase 19.4 / 2026091805`
- Release commit: `7126b378e031c6c7f5965d0f78b3e753b28c27df`
- Release: `source-v2026091805`
- Phase 19.4 feature CI: Run `35296525450` — SUCCESS
- Formal Release Run: `35300195897` — SUCCESS
- Online-update E2E: `2026091804 -> 2026091805` — SUCCESS
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

### 发布状态

- [x] VERSION / ver.txt / ver.json 已收口为 `2026091805`。
- [x] GitHub Release `source-v2026091805` 已发布。
- [x] 正式资产 `zonoe-online-update.zip` + `.sha256` 已发布。
- [x] Release Run `35300195897` 全部成功。
- [x] 真实 GitHub Release 在线更新 E2E `2026091804 -> 2026091805` 成功。
- [ ] 生产 BaoTa/Nginx 的 `/license` 路由仍需单独实机验证。
- [ ] iOS 明文/普通加密/V2 动态公告显示仍可继续做真机验收，但不影响在线更新发布。

## 保持不变的稳定边界

- 不改变旧 `appstore / appstore_v2` 公共字段、URL 和加密 envelope。
- 不改变卡密一次性消费、授权时长叠加及 `transfer_count` 剩余换绑次数语义。
- 不恢复 `App-mb.php` / `Index2.php`。
- 不无计划迁移 `fa_category` 物理字段。
- 远程 `dylib()` 内部授权逻辑继续延期，除非另开独立阶段。
