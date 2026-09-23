# Known Issues and Refactor Backlog

## P0 — 2026092207 production/manual verification pending

- Code fixes are committed and all three PR workflows are green, but the actual BaoTa/browser UI paths have not been manually exercised.
- Required checks:
  1. Trigger full scan while a same-source scan is active; old job/items must become `cancelled`, new full job must complete.
  2. Pause/resume parsing; UI must not expose `think\exception\HttpResponseException`.
  3. With `parse_enabled=1`, parsing must continue beyond the old 5-minute/hour/day quotas.
  4. Clear parse must retain IPA discovery rows, OpenList source configuration and `fa_category` while deleting/resetting only parse-derived state.
- Do not treat CI success as production/runtime verification.

## P1 — Full-scan cancellation is cooperative during row consumption

- A running old scan checks cancellation before/after OpenList calls and every 50 consumed rows.
- Therefore, if cancellation happens mid-batch, at most a small part of the current directory batch may still reach same-source asset upsert before the next cancellation check.
- The cancelled old job cannot complete full-scan reconciliation/mark-missing, and same-source asset upsert is designed to be idempotent enough for the restart path, but strict zero-post-cancel writes have not been runtime-tested.
- If production testing shows a race, tighten cancellation checking to each row or bind job activity validation directly to each write.

## P1 — Dedicated 2207 behavioral test coverage is incomplete

- Existing CI covers PHP 7.0 syntax/contracts, OpenList integration, MySQL 5.7 schema/concurrency/scale, scan safety and operations contracts.
- There is no dedicated end-to-end browser/runtime test that proves all four 2207 regression scenarios together.
- Keep PR `#22` draft until manual runtime verification or add targeted integration tests before release.

## P1 — Exact /license production Nginx interception

- `/authorization` is the supported online-update-safe authorization lookup route.
- ThinkPHP still retains `/license`, but production Nginx may intercept it through a case-insensitive LICENSE security rule before PHP.
- Exact `/license` requires changing/reloading the active BaoTa/Nginx vhost rule.

## Stable announcement contract

- Public announcement authorization time is single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons.
- Internal authorization scopes remain independent for permission enforcement.
