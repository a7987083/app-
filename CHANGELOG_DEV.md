# Development Changelog

## 2026-09-21 — Baseline cleanup / rollback to 2026091809

- Selected `source-v2026091809` / `21307ba5ec9b65ce0b5f0643555ccc7f7bec0ad9` as the active production/refactor baseline.
- Versions after `2026091809` are retired from the active line because they introduced Phase 20 / IPA Management Center and related persistence, scanner, parser, worker, binding, governance, write-back and deployment/runtime changes.
- Production has already been intentionally rolled back to `2026091809`; continuing from later releases would preserve architecture that is no longer part of the selected production baseline.
- The cleanup is intended to restore one unambiguous pre-Phase-20 baseline before refactoring. It does not assert that every later commit was independently defective.
- Post-1809 GitHub releases/tags/branches and generated release artifacts are designated for removal.
- Phase 20 database/runtime residue is to be cleaned separately after backup and verification.
- See `ROLLBACK_2026091809.md` for the full decision record.

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
