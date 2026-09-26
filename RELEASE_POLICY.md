# Release Policy

## Mandatory online update for every deliverable version

Every deliverable version of this repository must be published as a GitHub online-update release. A source-only version bump is not considered a complete release.

Required release artifacts and gates:

1. Bump `VERSION`, `public/update/ver.txt`, and `ver.json` to the same version.
2. Update `release/RELEASE_NOTES.md` and `PHASE13_RELEASE.txt`.
3. Ensure every runtime file changed by the release is present in `release/online-update-files.txt`.
4. Run the feature/release CI required for that change.
5. After the CI is green, the CI must automatically dispatch the canonical `ZONOE Source Release` workflow when `release/auto-release.env` has `AUTO_RELEASE=1`.
6. The automatic dispatcher must be idempotent: if `source-v<VERSION>` already exists, it must skip publishing rather than create a duplicate release.
7. A failed CI must never dispatch or publish a release.
8. The canonical release workflow must run PHP 7.0 regression, MySQL 5.7 migration checks and the applicable release gates.
9. Build `zonoe-online-update.zip` from the manifest.
10. Generate and verify `zonoe-online-update.zip.sha256`.
11. Publish GitHub Release `source-v<VERSION>`.
12. Run the real GitHub Release online-upgrade E2E from the previous stable version.
13. Do not call a version complete/stable until the Release, SHA256 asset, release gates, and online-upgrade E2E have succeeded.

## Automatic release contract

The persistent switch and canonical workflow are stored in `release/auto-release.env`.

Current contract:

```text
CI push run
  -> all CI jobs success
  -> read VERSION / public/update/ver.txt / ver.json
  -> versions must match
  -> read release/auto-release.env
  -> AUTO_RELEASE=1
  -> check GitHub Release source-v<VERSION>
     -> already exists: success + skip (idempotent)
     -> not found: dispatch ZONOE Source Release on the same tested branch
  -> release workflow runs its full regression/package/E2E pipeline
  -> publish source-v<VERSION>
```

Pull-request CI never publishes. Automatic publishing is only allowed from a successful push CI. New project CI workflows that can produce a deliverable version must reuse this same contract instead of inventing a second release path.

This rule applies to all future versions unless the repository owner explicitly changes the release policy.
