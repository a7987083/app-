# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026091806-announcement-expiry-authorization-hotfix`
- Stable phase/version: `Phase 19.4.1 / 2026091806`
- Release commit: `634b5ae92cb6009c99586f7301b73dd420965017`
- Release: `source-v2026091806`
- Hotfix CI: Run `35303572776` — SUCCESS
- Formal Release Run: `35303639979` — SUCCESS
- Online-update E2E: `2026091805 -> 2026091806` — SUCCESS

## Phase 19.4.1 — 已发布

- [x] 修复部分 App 授权无到期时间/剩余时间。
- [x] 当前授权优先级：全解锁 → 部分 App → 仅验证。
- [x] 新增 `[到期时间]`、`[剩余时间]`、`[部分到期时间]`、`[部分剩余时间]`。
- [x] 在线更新自动迁移公告旧变量。
- [x] 新增 `/authorization` 等价授权查询入口。
- [x] API Center 授权查询 URL 改为 `/authorization`。
- [x] 正式在线更新包与 SHA256 发布。
- [x] 1805 → 1806 GitHub Release 在线更新 E2E 通过。

## 环境级剩余项

- [ ] 若必须继续使用精确 URL `/license`，需要修改生产 BaoTa/Nginx 当前生效的大小写不敏感 LICENSE 拦截规则；这发生在 PHP 之前，不能由应用路由本身覆盖。
