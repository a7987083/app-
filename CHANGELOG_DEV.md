# Development Changelog

## 2026-09-26 — 2026092412 API Integration Center

Stable release: `source-v2026092412`.
Release branch: `release/2026092412-api-integration-center`.
Release target: `c03c2d7deb7bbac21918ef126f4c50c700f2d838`.
Latest release-gate code head: `004de0dee04cf1c7ca21abf1e1ab693823c1ddd7`.

### API contract

- Added `application/common/library/Ipa/DylibApiContract.php` as the canonical documentation contract for the existing Dylib verification APIs.
- Documented the real request fields: `udid`, `bundle_id`, `dylib_key`, `dylib_version`, `dylib_build`, `dylib_sha256`, `timestamp`, `nonce`, `signature`, plus v2 App identity fields.
- Documented the real response fields: `ok`, `code`, `action`, `offline_grace_seconds`, `token`, `message`, `server_time`, plus `access_level`, `permissions`, `app_identity`, `app_update`, and `notice`.
- Kept the established string result-code protocol; no incompatible numeric error-code layer was introduced.
- Added client guidance for current success/error codes and the four `action` values.

### API documentation / generated examples

- Objective-C Generator advanced to `2.2.0`.
- Generated OC remains a test/reference implementation, not business UI code.
- Generated package includes ten files, including `API_REFERENCE.md`, `ERROR_CODES.md`, and `EXAMPLES.md`.
- API reference includes exact Protocol v1/v2 canonical ordering and HMAC-SHA256 rules.
- Client logic is documented to branch on `ok + code`; `message` may be displayed but must not be parsed for business logic.
- Admin “接入说明” is now a detailed API reference with endpoints, request/response fields, HMAC and error codes.

### Release-gate fixes

- Updated stale 2405 UI-copy contract to assert actual Protocol v1 canonical and result-code semantics.
- Restored the `更新内容` Release Notes heading required by the real online-update E2E contract.
- Updated IPA Online Update Release Gate to validate dynamic `runtimeConfig.verify_path` rather than hard-code `/index/dylib_verify/verify` in the admin page.

### Verification

- OC Codegen CI #14 / Run `36239192712`: SUCCESS.
- ZONOE Source Release #253 / Run `36239620864`: SUCCESS.
- `php70-regression`: SUCCESS.
- `mysql57-migration`: SUCCESS.
- `phase19-3-1-http-load`: SUCCESS.
- `package-and-release`: SUCCESS.
- Real GitHub Release online-update E2E: SUCCESS.
- IPA Online Update Release Gate #152 / Run `36239878997`: SUCCESS.

### Formal asset

- `zonoe-online-update.zip`: 258203 bytes.
- SHA256: `72c0f72d24aed3642c42f3f8e0f39ac05c14c83613eddab65df6f3ac8ec0b9e8`.

### Not claimed

- No manual acceptance of an independent production OC/Swift client is claimed.
- Generated OC remains reference/test code; actual client UX and integration are owned by the client project.
