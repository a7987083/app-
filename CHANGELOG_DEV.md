# Development Changelog

## 2026-10-02 — 2026092435 Multi Device Keys

Stable release: `source-v2026092435`.
Release branch: `release/2026092435-multi-device-keys`.
Release target: `bda2656a699e93162ea38514f21e5027fc92ece9`.

### Device Key

- Authorization scope remains `UDID + Dylib Key`.
- Multiple independent P-256 PublicKeys may coexist under one authorization.
- Same BundleID with different Keychains and different App hosts no longer collide on one enrolled key.
- New keys still require active UDID `auth_proof`, one-time Challenge and valid ECDSA signature.
- Device Key uniqueness is `udid_hash + dylib_id + public_key_hash`.
- Stale Device Keys unused for 365 days are eligible for cleanup during enrollment.

### CI / compatibility cleanup

- Updated obsolete v1/v2 Dylib signing/lifecycle/runtime assertions to current Protocol v3.
- Updated runtime config signing checks to RSA-2048-SHA256.
- Updated IPA parser tests and Release Gate to current metadata-only Parser V2 launcher architecture.

### Verification

- Dylib Multi Device Keys 2435 CI #22: SUCCESS.
- IPA Online Update Release Gate #169: SUCCESS.
- ZONOE Source Release #348: SUCCESS.
- PHP 7.0 regression: SUCCESS.
- Source integrity: SUCCESS.
- MySQL 5.7 migration: SUCCESS.
- HTTP load gate: SUCCESS.
- Real GitHub Release online-update E2E: SUCCESS.
- Production real-device multi-App acceptance: not yet independently verified.
