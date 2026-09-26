# Known Issues and Refactor Backlog

## P0 — Verify Secret is embedded in generated/reference client code

- The current Dylib HMAC design requires the per-Dylib Verify Secret on the client side.
- Generated `*DylibConfig.m` therefore contains that secret.
- Generated/reference source must stay in controlled/private projects and must not be published to public repositories.
- 2412 documents this risk; it does not redesign the signing architecture.

## P1 — Admin API reference currently duplicates some canonical-contract text

- `DylibApiContract.php` is the canonical documentation source for request/response fields and result codes.
- The admin HTML currently renders a detailed static table that mirrors the contract for immediate usability.
- Future work should render the page dynamically from `DylibApiContract` to eliminate documentation drift.

## P1 — Client UI remains deliberately out of scope

- The verification center does not collect UDID, display card/license forms, open notice popups, create floating windows, or render license-information pages.
- It only returns API data such as `message`, `notice`, `app_update`, `permissions`, and `access_level`.
- Client projects must implement their own UX and should branch on `ok + code`, not `message` text.

## P1 — Real client integration acceptance pending

- CI verifies the API reference, error-code catalog, deterministic generated examples and online-update packaging.
- It does not prove an independent OC/Swift client has implemented the contract correctly.
- A real client should eventually be tested for canonical signing, nonce handling, result-code handling, notice/update consumption and offline-grace behavior.

## Compatibility boundaries

- Existing v1/v2 canonical signing order is unchanged by 2412.
- Existing string result codes remain the wire protocol; do not silently replace them with numeric codes.
- Legacy `Index::dylib()` / `Index::apiface()` remain separate compatibility APIs. Legacy `apiface` uses its existing signing contract and must not be conflated with the per-Dylib Verify Secret protocol.
- Historical release/tag commits must not be rewritten.
