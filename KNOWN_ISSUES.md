# Known Issues and Refactor Backlog

## P0 — 2026092405 real BaoTa Dylib lifecycle verification pending

- 2405 code/PR CI is green on exact pre-release HEAD `36e9104f...`: PHP 7.0, MySQL 5.7, Dylib signing/lifecycle/security contracts and iPhoneOS arm64 compile passed.
- Formal `source-v2026092405`, `2404 -> 2405` real GitHub Release online-update E2E and Final Gate are pending until the release commit completes.
- Required production checks: edit without changing Dylib Key, secret rotation semantics, disable/enable behavior, unused-item delete, referenced-item delete refusal, integration instructions and readable verification logs.

## P1 — Disabled Dylib intentionally resolves as unknown

- The existing verification service selects `dylib_key` with `enabled=1` and returns `dylib_unknown / block` when absent.
- 2405 keeps this protocol behavior unchanged. The admin UI describes it as “未注册或已停用”; no new disabled-specific protocol code is introduced.

## P1 — Dylib deletion is history-protected

- The schema has no Dylib soft-delete column and no foreign keys.
- A registration with any version, BundleID binding or verification log is not hard-deleted; the admin must disable it so audit and policy history remain intact.
- A completely unused registration may be hard-deleted after the existing second-confirmation flow.

## P0 — 2026092404 real BaoTa runtime verification remains pending

- 2404 FPM scan/parse, live refresh and software-source response fixes passed release CI/E2E but still require real BaoTa validation.
- Validate `discovered -> parsing -> parsed/parse_failed`, pause/resume/retry, manual/5-second refresh and software-source save/test behavior.

## P1 — PHP-FPM worker occupancy during in-process scan/parse

- `fastcgi_finish_request()` returns the browser response before queue consumption finishes, but that FPM worker remains occupied until its current scan/parse drain completes.
- Large OpenList trees and large parse queues still need production capacity observation.

## P1 — Cooperative cancellation / asset cleanup

- Active scan cancellation is cooperative and a small row batch may finish before the next cancellation check.
- IPA asset delete/clear must continue to preserve `fa_category` and actual OpenList files; real production data verification remains pending.

## P1 — Exact /license production Nginx interception

- `/authorization` remains the online-update-safe authorization query route.
- ThinkPHP still has `/license`, but production Nginx may intercept it before PHP.

## Stable announcement contract

- Public announcement authorization time remains single-clock only.
- Do not reintroduce separate full-source / partial-App / verify-only expiry buttons.
- Internal authorization scopes remain independent for permission enforcement.
