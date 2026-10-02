# Software Source Development Handoff

## Stable baseline

- Repository: `a7987083/app-`
- Stable release: `source-v2026092434`
- Stable target: `982a8ea6bdb1eca78a4fc9c3d12ca0069653b5da`

## Current development

- Version target: `2026092435`
- Branch: `work/2026092435-multi-device-keys`
- Focus: multi-Keychain / multi-App Device Key enrollment without BundleID coupling.

## Authentication model

Authorization scope remains:

`UDID + Dylib Key`

Credential rows are:

`UDID hash + dylib_id + public_key_hash`

A new PublicKey is accepted only when the UDID is currently authorized, the short-lived server `auth_proof` validates, and the one-time Challenge is signed by the matching P-256 private key.

There is no hard key-count limit. Existing enrolled keys update `last_used_at`. Keys unused for 365 days are deleted during a new enrollment, preventing permanent accumulation without limiting App count.

## Changed files

- `application/common/library/Ipa/DylibDeviceAuthService.php`
- `release/sql/2026092435_multi_device_keys.sql`
- `.github/workflows/dylib-multi-device-keys-2435-ci.yml`

## Verification status

- Modified: yes.
- Committed: yes, development branch.
- PHP/MySQL CI: pending actual run.
- Released: no.
- Production deployed: no.
- Real-device regression: no.
