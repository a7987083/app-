# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Stable baseline: `source-v2026092404`
- Active branch: `release/2026092405-dylib-lifecycle-integration`
- Pre-release code HEAD: `36e9104f6fe6b26cd7809f4d064f4016114add1f`
- Candidate: `source-v2026092405`
- Draft PR: `#24`
- Historical releases through 2404 must not be rewritten.

## 2026092405 implementation

1. `DylibCenter` supports edit, enable, disable and guarded delete. Dylib Key is immutable after registration because it is part of the existing signed lookup contract.
2. Disable preserves the row and all version/binding/log history. `DylibVerificationService` still queries `enabled=1`; disabled entries therefore fail closed using the existing `dylib_unknown / block` response.
3. The schema has no Dylib soft-delete column or foreign keys. Hard delete is allowed only when there are no `dylib_version`, `dylib_app_binding` or `dylib_verify_log` references; otherwise the admin must disable the Dylib.
4. Admin workflow is Register → Integration → Game authorization → Version control → Verification records.
5. Integration documentation is extracted from the real `DylibVerify` controller, `DylibVerificationService` and Objective-C `ZONVerifyClient`; endpoint remains `POST /index/dylib_verify/verify` and canonical HMAC order is unchanged.
6. Chinese labels are presentation-only. Raw protocol/storage enums and result codes remain unchanged.
7. Verification log keeps the stored fields unchanged; UI translates result/action and labels the truncated UDID hash clearly.
8. Delete keeps the existing `Layer.confirm`, `Fast.api.ajax`, Backend authorization and CSRF path.

## Verified evidence before release

- IPA Data Center CI `36020461657`: SUCCESS on exact HEAD `36e9104f...`.
- Regression Checks `36020461082`: SUCCESS on exact HEAD.
- Phase14 Production Hardening `36020461401`: SUCCESS on exact HEAD.
- IPA CI jobs: PHP 7.0 compatibility SUCCESS; MySQL 5.7 contracts SUCCESS; Dylib signing/lifecycle/security SUCCESS; iPhoneOS SDK arm64 verifier compile SUCCESS.
- Diff against 2404 base is limited to Dylib controller/view/JS and Dylib contract tests before release metadata.

## Release boundary

Not yet claimed at candidate stage: `source-v2026092405` published, `2404 -> 2405` online update E2E, Final Gate, or real BaoTa UI/runtime verification.

## Production verification after release

1. Update a real 2404 BaoTa installation to 2405 through the existing online updater.
2. Edit a Dylib name/policy; confirm key remains immutable and blank secret does not rotate it.
3. Disable it and call the existing client verify endpoint; expect `dylib_unknown` and `block`; enable it and confirm normal rules resume.
4. Try deleting an unused registration (allowed) and a registration with version/binding/log history (must be refused with instruction to disable).
5. Confirm the Integration panel matches the real Objective-C client request/signature contract.
6. Confirm version state and BundleID behavior are unchanged and verification records display Chinese labels without changing raw stored values.
