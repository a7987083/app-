# Development Changelog

## 2026-09-18 — Phase 19.4.2 / Release 2026091807

- Collapsed announcement expiry/remaining-time into one public authorization clock.
- Removed scope-specific expiry/remaining buttons from the announcement editor.
- Kept entitlement scopes internally separate with priority: full source > partial Apps > verify-only.
- Unified no-entitlement/expired text to `已过期或未解锁本源`.
- Simplified `[授权摘要]` to status + expiry + remaining (+ App count for partial authorization).
- Added idempotent migration `2026091807_unified_announcement_expiry.sql`.
- Retained runtime parsing of old scope-specific tokens but mapped every one to the generic clock.
- Feature CI Run `35306216020` — SUCCESS.
- Release Run `35306308086` — SUCCESS.
- Release `source-v2026091807` published.
- Online-update E2E `2026091806 -> 2026091807`: `self_update=passed progress=passed history=passed db_migration=yes`.
