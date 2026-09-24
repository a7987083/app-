# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092404`
- Active release branch: `release/2026092405-dylib-lifecycle-integration`
- Release commit: `f2cb8536b2a5196b4dab1c135c74033f740ed398`
- Current release: `source-v2026092405`
- Draft PR: `#24`
- Historical releases through 2404 must not be rewritten.

## 2026092405 implementation

1. `DylibCenter` supports edit, enable, disable and guarded delete. Dylib Key is immutable after registration because it is part of the existing signed lookup contract.
2. Disable preserves the row and all version/binding/log history. `DylibVerificationService` still queries `enabled=1`; disabled entries therefore fail closed using the existing `dylib_unknown / block` response.
3. The schema has no Dylib soft-delete column or foreign keys. Hard delete is allowed only when there are no `dylib_version`, `dylib_app_binding` or `dylib_verify_log` references; otherwise the admin must disable the Dylib.
4. Admin workflow is Register → Integration → Game authorization → Version control → Verification records.
5. Integration documentation is extracted from the real `DylibVerify` controller, `DylibVerificationService` and Objective-C `ZONVerifyClient`; endpoint remains `POST /index/dylib_verify/verify` and canonical HMAC order is unchanged.
6. Chinese labels are presentation-only. Raw protocol/storage enums and result codes remain unchanged.
7. Verification log keeps stored fields unchanged; UI translates result/action and labels the truncated UDID hash clearly.
8. Delete keeps the existing `Layer.confirm`, `Fast.api.ajax`, Backend authorization and CSRF path.

## Release evidence

- Pre-release IPA Data Center CI `36020461657`: SUCCESS.
- Regression Checks `36020461082`: SUCCESS.
- Phase14 Production Hardening `36020461401`: SUCCESS.
- Final IPA Online Update Release Gate `36021185353`: SUCCESS on release commit `f2cb8536...`.
- ZONOE Source Release `36021185414`: SUCCESS.
- PHP 7.0 / MySQL 5.7 / HTTP load / package publication: SUCCESS.
- Real GitHub Release online-update E2E `2404 -> 2405`: SUCCESS.
- GitHub Release `source-v2026092405` targets `f2cb8536b2a5196b4dab1c135c74033f740ed398`.
- Release ZIP: `zonoe-online-update.zip`, size `208933`, SHA256 `abc851d4e63fd98b78eda7ce06ff9cac73b5909380efbef3511a529bc949e0f4`, asset ID `586260722`.
- CI Artifact: `zonoe-source-2026092405-online-update`, ID `10816991706`, size `202158`, digest `sha256:91f2d63221448f9f858035c1672a17abe3082399db9b326d382a4ff7e2bb1a55`.

## Verification boundary

Verified: source/contract CI, PHP 7.0, MySQL 5.7, Dylib signing/lifecycle contracts, iPhoneOS arm64 client compile, HTTP load gate, real GitHub Release publication, `2404 -> 2405` online-update E2E, final release gate.

Not yet verified on the user's BaoTa host: actual admin UI interactions and a real production-client verify call after enable/disable.

## Production verification sequence

1. Use the existing updater on a real `source-v2026092404` BaoTa installation; expect `source-v2026092405`.
2. Edit Dylib name/policy; confirm Dylib Key remains immutable and blank secret does not rotate it.
3. Disable and call the existing verifier; expect `dylib_unknown / block`; re-enable and confirm existing version/BundleID rules resume.
4. Delete a never-used registration; it should succeed after second confirmation.
5. Attempt to delete a registration with version/binding/log history; it must refuse and instruct using disable.
6. Compare the Integration panel against the existing Objective-C client fields/signing order.
7. Confirm logs show readable Chinese labels while stored enum/result values remain unchanged.
