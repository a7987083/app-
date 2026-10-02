# Known Issues and Refactor Backlog

## 2026092435

- Real iOS production acceptance for many App/Keychain combinations remains an independent verification item; GitHub CI cannot substitute for device testing.
- 365-day Device Key cleanup is opportunistic during new enrollment. This keeps the implementation simple and prevents normal verification traffic from creating cleanup write load.
- `Auto Online Release Gate` remains disabled by repository policy (`AUTO_RELEASE=0`); canonical `ZONOE Source Release` is the authoritative release path and completed successfully.

## Compatibility boundaries

- Authorization remains `UDID + Dylib Key`.
- BundleID is not part of Device Key enrollment identity.
- Existing 2434 enrolled keys remain valid after migration.
- New independent keys require active UDID proof and valid P-256 Challenge signature.
- Protocol v3 canonical signing fields remain unchanged.
- Runtime config signing is RSA-2048-SHA256.
- IPA metadata parser is Parser V2; synchronous Mach-O enrichment is not part of that parse path.
