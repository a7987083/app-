# Development Changelog

## 2026-10-02 — 2026092435 Multi Device Keys

Base: `source-v2026092434` / `982a8ea6bdb1eca78a4fc9c3d12ca0069653b5da`.
Development branch: `work/2026092435-multi-device-keys`.

### Changes

- Replaced the single-key `UDID + Dylib` enrollment lookup with exact `UDID + Dylib + PublicKey` lookup.
- Multiple independent P-256 device keys may coexist below one active UDID + Dylib authorization.
- Removed the old `device_key_mismatch` rejection for a different but valid newly-enrolled key.
- New keys still require active UDID `auth_proof` and valid Challenge/ECDSA proof.
- Added 365-day stale Device Key cleanup using `last_used_at`.
- Added MySQL 5.7 migration `2026092435_multi_device_keys.sql`.
- Added `Dylib Multi Device Keys 2435 CI` definition covering PHP syntax, migration idempotency, multi-key uniqueness, and update-package inclusion.

### Verification

- Source changes: completed.
- Migration: added, not yet executed by CI/production.
- CI definition: added; run result pending.
- Production / real-device verification: not verified.
- Release: not published.
