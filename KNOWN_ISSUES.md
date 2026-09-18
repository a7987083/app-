# Known Issues and Refactor Backlog

## P1 — Exact /license production Nginx interception

- Application route/controller/view are present and released.
- `/authorization` is the supported online-update-safe equivalent route from 2026091806.
- Production `/license` can remain 404 while a case-insensitive Nginx security regex containing `LICENSE` intercepts the URI before ThinkPHP.
- Exact `/license` cannot be guaranteed by PHP/online-update files alone; the active BaoTa/Nginx vhost/rewrite rule must be changed and Nginx reloaded.

## Stable compatibility boundaries

- Do not change legacy `appstore / appstore_v2` public keys or encryption envelope.
- Keep one-time card activation and scope-separated entitlement semantics.
- Keep `transfer_count` as remaining device-transfer quota.
- Do not restore `App-mb.php` or `Index2.php`.
