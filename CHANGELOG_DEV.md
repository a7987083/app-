# Development Changelog

## 2026-09-18 — Phase 19.4.1 / Release 2026091806

- Fixed partial-App announcement expiry/remaining-time output.
- Effective authorization clock now uses priority: full source > partial Apps > verify-only.
- Added generic expiry/remaining variables and partial-specific variables.
- Added idempotent SQL migration from legacy full-source placeholders to generic placeholders.
- Added safe `/authorization` route while retaining `/license`.
- API Center now advertises `/authorization`.
- Hotfix CI Run `35303572776` — SUCCESS.
- Release Run `35303639979` — SUCCESS.
- GitHub Release `source-v2026091806` published.
- Real online-update E2E `2026091805 -> 2026091806`: `self_update=passed progress=passed history=passed db_migration=yes`.
