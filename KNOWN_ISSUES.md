# Known Issues and Refactor Backlog

## P0 — 2026092206 production/manual verification pending

- GitHub CI and online-update E2E are green, but the 2206 UI/worker-control fixes have not yet been manually verified on the real BaoTa deployment or a real client session.
- Required checks: software-source save/test behavior, pause/resume, offline-worker orphan recovery, clear-parse-results preservation boundaries.
- Do not treat CI success alone as real-device verification.

## P1 — Exact /license production Nginx interception

- `/authorization` is the supported online-update-safe authorization lookup route.
- ThinkPHP still retains `/license`, but production Nginx may intercept it through a case-insensitive LICENSE security rule before PHP.
- Exact `/license` requires changing/reloading the active BaoTa/Nginx vhost rule.

## Stable announcement contract

- Public announcement authorization time is single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons.
- Internal authorization scopes remain independent for permission enforcement.
