# Software Source Development Handoff

## Stable baseline

- Repository: `a7987083/app-`
- Stable branch: `release/2026091807-unified-announcement-expiry`
- Stable phase/version: `Phase 19.4.2 / 2026091807`
- Release commit: `1ef93306dec0a7b09bb99df55b785511d5b8b4c6`
- Release: `source-v2026091807`
- Unified-announcement CI: `35306216020` — SUCCESS
- Formal Release Run: `35306308086` — SUCCESS
- Online-update E2E: `2026091806 -> 2026091807` — SUCCESS

## Announcement contract

The announcement editor exposes one authorization clock only:

- `[授权状态]`
- `[到期时间]`
- `[剩余时间]`

Internal entitlement scopes remain separate. The effective clock is selected by priority:
whole source -> partial Apps -> verify-only.

If no active entitlement exists, all three public fields use:
`已过期或未解锁本源`.

Legacy scope-specific time tokens are hidden from the editor and mapped to the same generic clock at runtime. SQL migration `2026091807_unified_announcement_expiry.sql` rewrites historical templates to the generic tokens.

## Authorization page

`/authorization` remains the online-update-safe public lookup URL. `/license` remains routed in ThinkPHP but can still be intercepted by the production Nginx LICENSE rule before PHP.
