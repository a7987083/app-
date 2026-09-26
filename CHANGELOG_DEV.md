# Development Changelog

## 2026-09-26 — 2026092412 API Integration Center

Baseline: `source-v2026092411` with latest tested 2411 branch head `56f503c9eb094fe02cdc9987f25cd12edbf065f0`.
Development branch: `feature/2026092412-api-integration-center`.
Verified code checkpoint: `fc5a3d90398df60f2c651a113c253dce67e8d4db`.

### API contract

- Added `application/common/library/Ipa/DylibApiContract.php` as the canonical documentation contract for the existing Dylib verification APIs.
- Documented the real request fields: `udid`, `bundle_id`, `dylib_key`, `dylib_version`, `dylib_build`, `dylib_sha256`, `timestamp`, `nonce`, `signature`, plus v2 App identity fields.
- Documented the real response fields: `ok`, `code`, `action`, `offline_grace_seconds`, `token`, `message`, `server_time`, plus `access_level`, `permissions`, `app_identity`, `app_update`, and `notice`.
- Kept the established string result-code protocol; no incompatible numeric error-code layer was introduced.
- Added client guidance for all current success/error codes and the four `action` values.

### API documentation / generated examples

- Objective-C Generator advanced to `2.2.0`.
- Generated OC remains a test/reference implementation, not business UI code.
- Generated package now includes ten files, including:
  - `API_REFERENCE.md`
  - `ERROR_CODES.md`
  - `EXAMPLES.md`
- API reference includes exact Protocol v1/v2 canonical ordering and HMAC-SHA256 rules.
- Error documentation explicitly requires clients to branch on `ok + code`; `message` may be displayed but must not be parsed for business logic.
- Examples include cURL and Objective-C guidance; Swift/Python implementations must reproduce the same canonical format.

### Admin UX

- Dylib Center banner and Advanced tab now present the product as an API verification center.
- “接入说明” became a detailed API integration reference with endpoint, request, response, result-code, HMAC and integration-principle sections.
- Notice/update content is described as API-returned data; the client owns any popup/UI behavior.
- Generated-code panel was renamed/repositioned as “API 接入示例”.

### Packaging / verification

- Online-update manifest now includes `DylibApiContract.php`.
- OC Codegen CI #14 / Run `36239192712`: SUCCESS.
- PHP 7.0 lint/contracts: SUCCESS.
- JavaScript syntax: SUCCESS.
- Codegen API-document contract: SUCCESS.
- Dylib Center UX contract: SUCCESS.
- Online-update package content gate: SUCCESS.
- MySQL 5.7 migration regression: SUCCESS.

### Pending

- Formal 2412 release metadata/gates.
- `source-v2026092412` publication and online-update E2E.
- Real client project manual API integration remains outside CI and is not yet claimed as verified.
