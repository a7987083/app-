# Known Issues and Refactor Backlog

## P1 — Phase 20 production E2E not yet verified

- Phase 20.7.1–20.7.4 have passed repository contract/CI checks.
- Final verified code HEAD: `438db0823a2d03e220401509ce736da57bc99ab9`.
- Final code/UI CI Run: `35410113307` — SUCCESS.
- Real production OpenList mutation/recovery and MySQL Retention execution have not yet been run end-to-end against the production environment.
- Do not treat CI success as production verification.
- Before release candidate: verify batch governance, one explicit OpenList path mutation, failed/interrupted retry, ignore expiry, Range metrics, Retention preview/apply, audit linkage, and real admin permission behavior on a controlled dataset.
- Retention apply must not be enabled operationally until backup/restore has been validated for the target database.

## P1 — Exact /license production Nginx interception

- `/authorization` is the supported online-update-safe authorization lookup route.
- ThinkPHP still retains `/license`, but production Nginx may intercept it through a case-insensitive LICENSE security rule before PHP.
- Exact `/license` requires changing/reloading the active BaoTa/Nginx vhost rule.

## Stable announcement contract

- Public announcement authorization time is single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons.
- Internal authorization scopes remain independent for permission enforcement.
