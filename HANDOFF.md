# Software Source Development Handoff

## Current stable state

- Repository: `a7987083/app-`
- Stable release: `source-v2026092413`
- Release branch: `release/2026092413-api-doc-full-catalog`
- Release target / tested release head: `acfe570bef7113ae3dcf94b33ce8258f75ad33d7`
- OC Codegen CI #17 / Run `36250685197`: SUCCESS
- ZONOE Source Release #254 / Run `36251418295`: SUCCESS
- IPA Online Update Release Gate #153 / Run `36251418307`: SUCCESS
- Real GitHub Release online-update E2E: SUCCESS

## Product boundary

The Dylib verification center is an API provider. It owns server-side API contracts, verification, signing rules, runtime configuration, returned permission/notice/update data, logs, and integration documentation. Client-side UDID acquisition, card/license input UI, popups, floating menus and product UX remain outside this center.

## 2413 implementation

1. Admin API guide lists 8 existing entries and distinguishes JSON, HTML compatibility, and Legacy entry types.
2. `/authorization` and `/unbind` are not documented as pure JSON APIs.
3. Protocol v1/v2 HMAC-SHA256 canonical remains unchanged and has a dedicated documentation section.
4. `DylibApiDocumentation` generates the downloadable documentation package.
5. `DylibApiDocs` exposes the authenticated admin ZIP download.
6. ZIP export contains exactly 13 integration files and uses current Dylib/runtime `verify_path` context.
7. Export never includes the real Verify Secret; examples use `<VERIFY_SECRET>`.
8. New contract tests execute the document generator and validate endpoint count, file count, JSON schemas, dynamic verify path and secret hygiene.

## Formal online-update asset

- Release: `source-v2026092413`
- Release ID: `397283396`
- Asset: `zonoe-online-update.zip`
- Size: 266878 bytes
- SHA256: `eec0cb86dcf5143c0b272f2a143d1a38a2b685c62b76a4bb9d308ff4990a96a8`

## Verified

- Feature CI #17: passed.
- PHP 7.0 regression: passed.
- MySQL 5.7 migration: passed.
- HTTP concurrency/load gate: passed.
- Online-update package: passed.
- Source Release packaging/publication: passed.
- Real GitHub Release online-update E2E: passed.
- IPA Online Update Release Gate #153: passed.

## Not claimed

- A separate production OC/Swift client has not been manually accepted against the APIs.
- Browser/manual admin UI click-through is not claimed by CI alone.

## Next work

Use `source-v2026092413` as the stable baseline. Preserve the existing wire protocols and keep client UX outside the Dylib verification center. If API documentation is changed again, update the generated documentation model and the admin presentation together.
