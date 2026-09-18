# Software Source Development Handoff

## Repository / stable baseline

- Repository: `a7987083/app-`
- Stable branch: `release/2026091806-announcement-expiry-authorization-hotfix`
- Stable phase/version: `Phase 19.4.1 / 2026091806`
- Release commit: `634b5ae92cb6009c99586f7301b73dd420965017`
- Release: `source-v2026091806`
- Hotfix CI Run: `35303572776` — SUCCESS
- Formal Release Run: `35303639979` — SUCCESS
- Online-update E2E: `2026091805 -> 2026091806` — SUCCESS
- Online-update assets: `zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`

## 2026091806 hotfix

### Announcement authorization clock

Generic `[到期时间]` / `[剩余时间]` now choose the effective authorization by strict priority:

1. whole-source authorization;
2. partial App authorization;
3. verification-only authorization.

Partial App status uses the latest active App-scoped card expiry. Existing announcement templates using `[全源到期时间]` / `[全源剩余时间]` are migrated by `release/sql/2026091806_announcement_priority.sql`.

### Authorization page route

- Legacy application route `/license` remains.
- Safe equivalent route `/authorization` maps to the same `Index::license()` action.
- API Center now advertises `/authorization`.

The production `/license` 404 is outside ThinkPHP: a case-insensitive Nginx LICENSE security rule intercepts that URI before PHP. Online update can update the site files but cannot reliably modify/reload the active BaoTa Nginx vhost. Therefore `/authorization` is the online-update-safe route; exact `/license` requires a one-time active Nginx rule change.
