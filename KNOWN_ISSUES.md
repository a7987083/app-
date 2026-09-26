# Known Issues and Refactor Backlog

## P0 — 2410 destructive Dylib deletion requires real-admin acceptance

- `deleteDylib()` now intentionally removes the Dylib registration together with its versions, legacy App bindings, and verification logs in one transaction.
- This is a behavior change from 2409, which required disabling a Dylib once history existed.
- CI validates syntax/contracts but does not prove production database contents or operator intent.
- Before 2410 release, test with a disposable Dylib containing at least one version and confirm only the intended rows are deleted.

## P0 — Version deletion can invalidate installed clients

- `deleteVersion()` is intentionally unrestricted after confirmation.
- Any client still reporting the deleted version will resolve as `version_unknown` until that version is re-registered or the client updates.
- Before deleting a production version, confirm rollout state and current active clients.

## P1 — Version edit/delete UI still needs real browser verification

- Backend update path existed before 2410; 2410 exposes it through table controls and adds delete.
- PHP/JS contracts are green, but FastAdmin/Bootstrap Table behavior should be checked in the real BaoTa admin once.

## P1 — Legacy `Index::dylib()` / `Index::apiface()` remain compatibility APIs

- Both methods still exist in `application/index/controller/Index.php`; 2410 does not duplicate or fork their server implementation.
- OC Generator 2.1.0 now exports their candidate URLs from configured API endpoints.
- Legacy `apiface` response signing currently uses `udid|expire|ts|nonce`; new Dylib v2 verification remains a separate protocol.
- Do not merge the two signing contracts or silently replace the legacy endpoints.

## P1 — Generated Objective-C ZIP contains the shared Dylib verify secret

- This is required by the current client HMAC design.
- Generated source must remain in controlled/private projects and must not be published to a public repository.
- Backend/database/admin secrets are not exported.

## P1 — Real generated ZIP integration pending

- CI confirms deterministic generation and legacy URL inclusion.
- Still required: download one generated ZIP from the real admin and compile/integrate it in a real Dylib project.

## Stable compatibility boundary

- `source-v2026092409` remains the current stable release until 2410 formal release gates complete.
- 2409 v1/v2 HMAC, Bootstrap/runtime config, App identity, and database contracts are unchanged by the 2410 CRUD work.
- Historical release/tag commits must not be rewritten.
