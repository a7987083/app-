# Development Changelog

## 2026-09-26 — 2026092413 Dylib API Full Catalog

Stable release: `source-v2026092413`.
Release branch: `release/2026092413-api-doc-full-catalog`.
Release target: `acfe570bef7113ae3dcf94b33ce8258f75ad33d7`.

### API catalog / documentation

- Expanded the Dylib Center API guide from verification-only documentation to an 8-entry catalog.
- Explicitly labels JSON endpoints, HTML page compatibility endpoints, and Legacy APIs.
- Moved Protocol v1/v2 HMAC rules into a dedicated signature section without changing the wire contract.
- Added `DylibApiDocumentation.php` for generated integration material and `DylibApiDocs.php` for admin ZIP download.
- Added “全部下载 ZIP” for the currently selected Dylib.
- Export contains 13 files: README, API overview/reference, error codes, signature, response model, flow, cURL/Objective-C/Swift/Python examples, and two JSON schemas.
- Real Verify Secret is never exported; examples use `<VERIFY_SECRET>`.
- `/authorization` and `/unbind` are documented as HTML page compatibility entries rather than pure JSON APIs.

### Verification

- Feature CI: OC Codegen CI #17 / Run `36250685197`: SUCCESS.
- Source Release #254 / Run `36251418295`: SUCCESS.
- PHP 7.0 regression: SUCCESS.
- MySQL 5.7 migration: SUCCESS.
- Phase 19.3.1 HTTP load gate: SUCCESS.
- Package and GitHub Release publication: SUCCESS.
- Real GitHub Release online-update E2E: SUCCESS.
- IPA Online Update Release Gate #153 / Run `36251418307`: SUCCESS.

### Formal asset

- Release ID: `397283396`.
- `zonoe-online-update.zip`: 266878 bytes.
- SHA256: `eec0cb86dcf5143c0b272f2a143d1a38a2b685c62b76a4bb9d308ff4990a96a8`.

### Not claimed

- No manual third-party production OC/Swift client acceptance is claimed.
- The admin page still contains some static explanatory markup; the exported docs are generated, but further consolidation can reduce documentation drift.
