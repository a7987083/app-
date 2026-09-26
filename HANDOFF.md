# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Stable release: `source-v2026092411`
- Stable release target: `b46da637df7c0c7918b4ec71b6fe2bdf2387a73c`
- Latest tested 2411 branch head: `56f503c9eb094fe02cdc9987f25cd12edbf065f0`
- Development branch: `feature/2026092412-api-integration-center`
- Verified 2412 code checkpoint: `fc5a3d90398df60f2c651a113c253dce67e8d4db`
- OC Codegen CI #14 / Run `36239192712`: SUCCESS.

## 2412 product boundary

The Dylib verification center is an API provider. It owns verification endpoints, HMAC rules, result codes, runtime configuration, API-returned notice/update data, logs, and integration documentation. It does not own UDID collection UI, license/card input UI, popup presentation, floating-window UI, or license-information pages. Those are client-project responsibilities.

## 2412 implementation

1. Added `DylibApiContract` as the canonical documentation model for the existing wire protocol.
2. Kept existing string `code` values instead of adding an incompatible numeric-code protocol.
3. Documented exact v1/v2 request fields and canonical HMAC-SHA256 ordering.
4. Documented actual response fields including `ok`, `code`, `action`, `offline_grace_seconds`, `token`, `message`, and v2/extras.
5. Objective-C Generator 2.2.0 is now explicitly a test/reference API integration generator.
6. Generated package adds `API_REFERENCE.md`, `ERROR_CODES.md`, and `EXAMPLES.md` while retaining existing OC sample/config files.
7. Admin integration guide now exposes endpoint, request, response, error-code and signature documentation directly in the Dylib Center.
8. Client guidance: branch on `ok + code`; show `message` if desired; never parse message text to infer business state.
9. Online-update manifest includes `application/common/library/Ipa/DylibApiContract.php`.

## Verified

- PHP 7.0 syntax/contracts: passed.
- JavaScript syntax: passed.
- Deterministic codegen contract with 10 generated files: passed.
- API reference/error-code assertions: passed.
- Dylib Center UX contract: passed.
- Online-update package content gate: passed.
- MySQL 5.7 migration regression: passed.

## Not yet verified / not claimed

- Formal 2412 Release Gate and GitHub Release are pending.
- Real OC/Swift client integration against production API has not been manually accepted.
- Generated OC is reference/test code; CI success does not prove a third-party client implementation.

## Next task

Create `release/2026092412-api-integration-center`, write 2412 release metadata, run the formal Source Release + IPA Online Update Release Gate, verify online-update E2E, and publish `source-v2026092412` with ZIP/SHA256 assets.
