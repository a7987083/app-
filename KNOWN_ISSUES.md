# Known Issues and Refactor Backlog

## P0 — Verify Secret remains a client-side protocol secret

- The current per-Dylib HMAC design requires the Verify Secret in controlled client implementations.
- Reference/generated client source containing a real secret must stay private.
- 2413 improves documentation export hygiene: the “全部下载 ZIP” package never exports the real Verify Secret and uses `<VERIFY_SECRET>` instead.
- 2413 does not redesign the underlying signing architecture.

## P1 — Admin API presentation still contains static explanatory markup

- `DylibApiContract.php` remains the canonical verification-contract source.
- `DylibApiDocumentation.php` now generates the downloadable integration package.
- The admin page has the complete 8-entry catalog but still contains some static HTML/text; future work should further render presentation from shared structured data to reduce drift.

## P1 — HTML compatibility entries must not be treated as JSON APIs

- `/authorization` and `/unbind` currently render HTML pages through their Controller flows.
- Programmatic clients must not assume these endpoints return JSON.
- `/unbind/query` is the explicit JSON status-query entry.

## P1 — Real client/manual admin acceptance pending

- CI verifies PHP 7.0, MySQL 5.7, HTTP load, API documentation generation, online-update packaging, Release Gate, and real GitHub Release upgrade E2E.
- CI does not prove an independent production OC/Swift client has integrated the APIs correctly.
- CI also does not replace manual browser click-through of the Dylib Center UI.

## Compatibility boundaries

- Existing Protocol v1/v2 canonical signing order is unchanged by 2413.
- Existing string result codes remain the wire protocol.
- `runtimeConfig.verify_path` is configurable and must not be hard-coded in future docs/tests.
- Legacy `Index::dylib()` / `Index::apiface()` remain compatibility APIs with their existing contracts.
- Historical release/tag commits must not be rewritten.
