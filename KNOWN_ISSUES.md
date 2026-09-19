# Known Issues and Refactor Backlog

## P1 — Phase 20 production E2E not yet verified

- Phase 20.7.1–20.7.3 have passed repository contract/CI checks.
- Real production OpenList mutation/recovery and MySQL retention execution have not yet been run end-to-end against the production environment.
- Do not treat CI success as production verification.
- Before release candidate: verify batch governance, failed/interrupted retry, Range metrics, Retention preview, Retention apply, and audit linkage on a controlled production/staging dataset.

## P1 — Exact /license production Nginx interception

- `/authorization` is the supported online-update-safe authorization lookup route.
- ThinkPHP still retains `/license`, but production Nginx may intercept it through a case-insensitive LICENSE security rule before PHP.
- Exact `/license` requires changing/reloading the active BaoTa/Nginx vhost rule.

## Stable announcement contract

- Public announcement authorization time is single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons.
- Internal authorization scopes remain independent for permission enforcement.
