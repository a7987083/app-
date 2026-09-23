# Known Issues and Refactor Backlog

## P0 — 2026092207 production/manual verification pending

- `source-v2026092207` 已发布，正式 Source Release CI、GitHub Release 在线升级 E2E 和在线更新 Artifact 均已通过/生成。
- 但实际 BaoTa/browser UI 路径尚未人工执行，不能把 CI/E2E 等同于生产运行验证。
- Required checks:
  1. Trigger full scan while a same-source scan is active; old job/items must become `cancelled`, new full job must complete.
  2. Pause/resume parsing; UI must not expose `think\exception\HttpResponseException`.
  3. With `parse_enabled=1`, parsing must continue beyond the old 5-minute/hour/day quotas.
  4. Clear parse must retain IPA discovery rows, OpenList source configuration and `fa_category` while deleting/resetting only parse-derived state.

## P1 — Full-scan cancellation is cooperative during row consumption

- A running old scan checks cancellation before/after OpenList calls and every 50 consumed rows.
- If cancellation happens mid-batch, a small part of the current directory batch may still reach same-source asset upsert before the next cancellation check.
- The cancelled old job cannot complete full-scan reconciliation/mark-missing.
- If production testing shows a race, tighten cancellation checking to each row or bind job activity validation directly to each write.

## P1 — Dedicated 2207 browser/runtime behavioral coverage remains incomplete

- Formal release CI now covers PHP 7.0 regression, source integrity, MySQL 5.7 migrations, HTTP concurrency/load, release packaging and GitHub Release online-upgrade E2E.
- It still does not reproduce all four 2207 regression scenarios through the real BaoTa/browser UI and production OpenList data.
- Keep PR `#22` draft until manual runtime verification unless explicitly instructed otherwise.

## P1 — Exact /license production Nginx interception

- `/authorization` is the supported online-update-safe authorization lookup route.
- ThinkPHP still retains `/license`, but production Nginx may intercept it through a case-insensitive LICENSE security rule before PHP.
- Exact `/license` requires changing/reloading the active BaoTa/Nginx vhost rule.

## Stable announcement contract

- Public announcement authorization time is single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons.
- Internal authorization scopes remain independent for permission enforcement.
