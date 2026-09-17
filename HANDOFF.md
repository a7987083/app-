# Software Source Development Handoff

## Repository / stable baseline

- Repository: `a7987083/app-`
- Stable branch: `release/2026091801-phase19-client-sync`
- Stable phase/version: `Phase 19.1 / 2026091801`
- Release commit: `6e3c7a3af25843c3f329207bd6b4c4e06e182e57`
- Release: `source-v2026091801`
- Feature CI: `35261910066` — SUCCESS
- Release CI: `35262273073` — SUCCESS
- Online-update E2E: `2026091714 -> 2026091801` — SUCCESS
- `main` is stale and is not the active baseline.

## What Phase 19.1 changed

Phase 18 already provided additive V3 endpoints. Phase 19.1 makes those endpoints safe for a transactional local client database.

### Full sync

`/appstore/v3/apps` now accepts `snapshot_revision`.

- First page establishes a snapshot revision.
- Following pages keep the same revision.
- `SourceSyncV3::page()` checks the change-log revision before and after the page query.
- If the snapshot changed, response contains `snapshot_valid=0` and `restart_required=1` with no mixed app rows.
- A client must roll back its temporary SQLite transaction and restart from `after_id=0` using the new revision.

### Delta sync

`SourceChangeLog::minDeltaSince()` defines the oldest client revision that can still consume every retained change.

- meta exposes `min_delta_since`.
- delta exposes `min_since`, `reset_required`, `reset_reason`.
- `history_gap`: local revision is older than retained change history; perform full sync.
- `future_revision`: local revision is ahead of server current revision; perform full sync.
- Normal delta retains `next_since`, `has_more`, `upserts`, `deleted`.

### Compatibility

- Legacy `/appstore` is unchanged.
- `source_v3=0` remains `supported=0` + `fallback=appstore`.
- Authorization/blacklist/business failures remain `supported=1`; fallback cannot bypass them.
- Plain / normal encryption / V2 encryption behavior is unchanged.

## Online update path

`admin/general/Config`
-> `UpdateManager`
-> `GitHubUpdateSource`
-> HTTPS + SHA256
-> `UpdateInstaller`
-> file/database backup
-> SQL/file install
-> integrity verification
-> `UpdateRuntimeStore` history/status.

Phase19 runtime files are already in `release/online-update-files.txt`:

- `application/index/controller/SourceV3.php`
- `application/common/library/SourceSyncV3.php`
- `application/common/library/SourceChangeLog.php`

The release pipeline built and published `zonoe-online-update.zip` plus `.sha256`. The real Release E2E resolved previous stable version `2026091714` and finished with:

`OK ... real_release=2026091801 base=2026091714 self_update=passed progress=passed history=passed db_migration=yes`

## Validation status

Verified:

- PHP 7.0 Phase18 compatibility + Phase19 contract tests.
- MySQL 5.7 source-change migration and retention-window tests.
- Full PHP 7.0 regression.
- Full MySQL 5.7 migration chain.
- Release metadata and source integrity manifest.
- Online-update ZIP content and SHA256.
- Real GitHub Release download/install E2E from `2026091714` to `2026091801`.

Not claimed:

- Production BaoTa server/browser smoke for `2026091801`.
- iOS real-device SQLite synchronization.

## Client-source boundary

This repository contains the server. Repository code search finds no Objective-C and no `sqlite3` client implementation. Therefore do not claim that the iOS browser client itself has been modified in this repo.

The next engineer/AI must locate the actual client repository and implement Phase 19.2 against the now-stable server contract:

1. probe `/v3/meta`;
2. transactional SQLite full sync with `snapshot_revision`;
3. delta upsert/delete with `next_since`;
4. full reset on `restart_required/reset_required`;
5. fallback only on protocol unsupported (`supported=0`);
6. real-device validation for plain/normal/V2 encryption and authorization/blacklist cases.

## Stable compatibility boundaries

- Do not change public legacy AppStore field/encryption semantics without a separately versioned protocol.
- Keep existing card duration, one-time activation, entitlement stacking and transfer quota behavior.
- Keep BaoTa deployment/update contract and both Nuosike/GitHub updater paths.
- Do not restore `App-mb.php` or `Index2.php`.
- Do not physically migrate legacy Category columns without a dedicated migration and rollback plan.

See `ROADMAP.md`, `PROJECT_STATE.json`, `KNOWN_ISSUES.md`, and `CHANGELOG_DEV.md` before starting new work.
