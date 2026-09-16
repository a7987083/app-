# Release Policy

## Mandatory online update for every deliverable version

Every deliverable version of this repository must be published as a GitHub online-update release. A source-only version bump is not considered a complete release.

Required release artifacts and gates:

1. Bump `VERSION`, `public/update/ver.txt`, and `ver.json` to the same version.
2. Update `release/RELEASE_NOTES.md` and `PHASE13_RELEASE.txt`.
3. Ensure every runtime file changed by the release is present in `release/online-update-files.txt`.
4. Run PHP 7.0 regression and MySQL 5.7 migration checks required by the release workflow.
5. Build `zonoe-online-update.zip` from the manifest.
6. Generate and verify `zonoe-online-update.zip.sha256`.
7. Publish or refresh GitHub Release `source-v<VERSION>`.
8. Run the real GitHub Release online-upgrade E2E from the previous stable version.
9. Do not call a version complete/stable until the Release, SHA256 asset, and online-upgrade E2E have succeeded.

This rule applies to all future versions unless the repository owner explicitly changes the release policy.
