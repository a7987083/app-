# Software Source Development Handoff

## Current stable state

- Repository: `a7987083/app-`
- Stable release: `source-v2026092435`
- Release branch: `release/2026092435-multi-device-keys`
- Release target: `bda2656a699e93162ea38514f21e5027fc92ece9`
- ZONOE Source Release #348 / Run `36973103572`: SUCCESS
- IPA Online Update Release Gate #169 / Run `36973103536`: SUCCESS
- Dylib Multi Device Keys 2435 CI #22 / Run `36973103510`: SUCCESS

## 2435 authentication model

Authorization scope:

`UDID + Dylib Key`

Credential identity:

`udid_hash + dylib_id + public_key_hash`

Multiple independent device keys may coexist without BundleID coupling. A new key requires active UDID proof plus one-time Challenge/ECDSA verification. Existing keys refresh `last_used_at`. Keys unused for 365 days are cleaned opportunistically during enrollment.

## Release asset

- Asset: `zonoe-online-update.zip`
- Size: 298749 bytes
- SHA256: `d0d00573669de71e41dc4d8fe1aec71a3af3127b8eccce055f4fd04376a0c82a`

## Verification boundary

- Repository/CI/release/online-update E2E: verified.
- Production database deployment: not asserted from GitHub CI alone.
- Real iOS multi-App / same-BundleID-different-Keychain acceptance: not yet independently verified.
