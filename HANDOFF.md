# Software Source Development Handoff

## Current state

- Repository: `a7987083/app-`
- Previous stable release: `source-v2026092206`
- Active/release branch: `release/2026092207-ipa-controls-regression-fix`
- 2207 release commit: `49372574305bbf2e52b6b1965e4151a142de8a8a`
- Current release: `source-v2026092207`
- Validation PR: `#22` (draft; keep draft until production/manual runtime verification unless explicitly instructed otherwise)

## 2207 implementation

1. Full scan: incremental remains mutually exclusive; full scan cancels active same-source jobs/items and creates a new full job. Cancellation is cooperative during row consumption.
2. Parse limits: persisted quota fields remain for compatibility, but only `parse_enabled` blocks new claims.
3. UI: Worker-status/rate-limit panel removed from IPA Center; internal `WorkerState` remains for orphan recovery.
4. Pause/resume: targeted framework success responses are outside broad exception catches, avoiding normal `HttpResponseException` being displayed as a failure.
5. Clear parse: preserves IPA discovery/OpenList source rows and `fa_category`; clears/reset only parse-derived data.

## Release evidence

- Online Update Release Gate Run `35929007415`: SUCCESS.
- ZONOE Source Release Run `35930081424`: SUCCESS.
- Release `source-v2026092207` targets commit `49372574305bbf2e52b6b1965e4151a142de8a8a`.
- Release assets: `zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`.
- Release ZIP size: `200266` bytes; SHA256: `6f828567e3b3d535f8dab60de4fa543d620818b3a1be2189a3f9ef324389f5fc`.
- CI Artifact: `zonoe-source-2026092207-online-update`, ID `10780897151`, size `193451` bytes, digest `sha256:afa9181e40a75cd40dc5ada936a1de9a3e14e71e6214547a4752f6fa0cecb44c`.
- Real GitHub Release online-update E2E from previous stable release to 2207: SUCCESS.

## Verification boundary

Verified: source diff, PHP 7.0 regression, source integrity, MySQL 5.7 migration tests, concurrency/load gate, update-package build, GitHub Release publishing, SHA256 asset, and GitHub Release online-update E2E.

Not verified: actual BaoTa deployment, browser click path, live production OpenList timing/data, manual pause/resume UI, production clear-parse result.

## Next task

Run the real BaoTa/test deployment online updater from 2206 to 2207 and manually exercise:
1. active scan -> full re-scan replacement;
2. pause/resume without `think\exception\HttpResponseException`;
3. parsing beyond old quota limits;
4. clear parse preserving IPA discovery rows and `fa_category`.

Do not rewrite or replace historical `source-v2026092206`.
