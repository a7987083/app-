# Software Source Development Handoff

## Current stable state

- Repository: `a7987083/app-`
- Stable release: `source-v2026092412`
- Release branch: `release/2026092412-api-integration-center`
- Release target: `c03c2d7deb7bbac21918ef126f4c50c700f2d838`
- Latest release-gate code head: `004de0dee04cf1c7ca21abf1e1ab693823c1ddd7`
- OC Codegen CI #14 / Run `36239192712`: SUCCESS
- ZONOE Source Release #253 / Run `36239620864`: SUCCESS
- IPA Online Update Release Gate #152 / Run `36239878997`: SUCCESS

## Product boundary

The Dylib verification center is an API provider. It owns verification endpoints, HMAC rules, result codes, runtime configuration, API-returned notice/update/permission data, logs, and integration documentation. It does not own UDID collection UI, license/card input UI, popup presentation, floating-window UI, or license-information pages.

## 2412 implementation

1. `DylibApiContract` is the canonical documentation model for the existing wire protocol.
2. Existing string `code` values remain the protocol; no incompatible numeric code layer was added.
3. Protocol v1/v2 request fields and exact HMAC-SHA256 canonical ordering are documented.
4. Actual response fields include `ok`, `code`, `action`, `offline_grace_seconds`, `token`, `message`, `server_time` and v2 extras.
5. Objective-C Generator 2.2.0 is explicitly a test/reference API integration generator.
6. Generated package contains `API_REFERENCE.md`, `ERROR_CODES.md`, and `EXAMPLES.md` plus the existing sample/config files.
7. Admin API guide exposes endpoint, request, response, result-code and signature documentation directly.
8. Client guidance: branch on `ok + code`; show `message` if desired; never parse message text to infer business state.
9. Release Gate validates the configurable `runtimeConfig.verify_path`, not a hard-coded verify URL.

## Verified

- PHP 7.0 syntax/contracts: passed.
- JavaScript syntax: passed.
- Deterministic 10-file codegen contract: passed.
- API reference/error-code assertions: passed.
- Dylib Center UX contract: passed.
- Online-update package content gate: passed.
- MySQL 5.7 migration regression: passed.
- Source Release packaging: passed.
- Real GitHub Release online-update E2E: passed.
- IPA Online Update Release Gate #152: passed.

## Formal online-update asset

- `zonoe-online-update.zip`
- Size: 258203 bytes
- SHA256: `72c0f72d24aed3642c42f3f8e0f39ac05c14c83613eddab65df6f3ac8ec0b9e8`

## Not claimed

- A separate production OC/Swift client has not been manually accepted against the API.
- Generated OC is reference/test code and does not prove third-party client correctness.

## Next work

Use 2026092412 as the stable baseline. Future work should keep API/server responsibilities separate from client UI and should render more admin documentation directly from `DylibApiContract` to reduce protocol-documentation drift.
