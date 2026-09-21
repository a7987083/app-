# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable version: `2026091809`
- Stable tag: `source-v2026091809`
- Stable commit: `21307ba5ec9b65ce0b5f0643555ccc7f7bec0ad9`

## Baseline cleanup

- [x] Select `2026091809` as the rollback/refactor baseline.
- [x] Retire Phase 20 / IPA Management Center from the active baseline.
- [x] Record why versions after `2026091809` are being removed.
- [ ] Remove GitHub releases/tags/branches newer than `2026091809` where the GitHub write interface permits.
- [ ] Verify no post-1809 runtime/database residue remains on production.

## Refactor sequence

1. Audit `2026091809` architecture and data flow without changing behavior.
2. Identify controller/database coupling, duplicated logic, performance risks and deployment/update coupling.
3. Add characterization/regression tests before structural changes.
4. Refactor in small stages from the `2026091809` baseline.
5. Reconsider IPA management only after the core architecture is stable; do not restore the old Phase 20 implementation wholesale.

See `ROLLBACK_2026091809.md` for the rollback rationale.
