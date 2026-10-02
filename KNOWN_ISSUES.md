# Known Issues and Refactor Backlog

## 2026092435 — pending verification

- Multi-Device-Key implementation is committed on `work/2026092435-multi-device-keys` but CI has not yet produced a verified run result.
- The MySQL 5.7 migration has not yet been applied to production.
- Real iOS multi-App / same-BundleID-different-Keychain behavior remains unverified on device.
- 365-day cleanup is intentionally opportunistic: it runs when a new Device Key is enrolled. This avoids a new scheduler and keeps the implementation simple. If no new keys are ever enrolled, stale rows may remain, but the table is not growing from that condition.

## Compatibility boundaries

- Authorization remains `UDID + Dylib Key`.
- BundleID is not part of Device Key enrollment identity.
- Existing 2434 enrolled keys remain valid after migration.
- New independent keys require active UDID proof and valid P-256 Challenge signature.
- Protocol v3 canonical signing fields remain unchanged.
