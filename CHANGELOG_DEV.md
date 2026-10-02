# Development Changelog

## 2026-10-02 — 2026092436 Long-Term Retention Hardening

Base: `source-v2026092435` / `bda2656a699e93162ea38514f21e5027fc92ece9`.
Development branch: `work/2026092436-retention-step1`.

### Database retention

- Removed retired `fa_dylib_nonce` access from `ipa:maintenance`.
- Added 1-day retention for `fa_dylib_auth_challenge`.
- Session cleanup keeps rows until one day after expiry.
- Dylib verify logs retain the configured window, default 30 days.
- API request logs retain 30 days by default.
- Device public keys unused for 365 days are now cleaned by daily maintenance, not only during new enrollment.
- IPA parse attempts retain 30 days.
- Terminal scan items retain 7 days; terminal scan jobs retain 30 days.
- Authorization events, card-transfer logs, admin logs and source-change logs retain 365 days by default.
- Missing IPA assets are only deleted after 365 days and only when no manual category binding exists; related derived rows are removed transactionally.

### Operational retention

- Runtime PHP logs older than 30 days are deleted with a bounded per-run file limit.
- Existing UpdateOps retention is now invoked by daily maintenance:
  - status: 14 days
  - history: 90 days
  - rollback backups: 30 days
- Maintenance systemd service/timer are included in the online-update package.

### Safety / scale

- High-volume table deletes use bounded primary-key batches: 5,000 rows/batch, max 20 batches per run.
- Scan cleanup uses cursors so empty early job windows cannot permanently block later stale rows.
- Added MySQL 5.7 idempotent indexes for retention scans.
- Dedicated 2436 CI covers PHP syntax/contracts, MySQL 5.7 double-apply migration and online-update package contents.

### Verification

- Source changes: completed.
- Dedicated retention CI #28: SUCCESS (PHP contract, MySQL 5.7 double-apply, online-update package).
- Full release gate: not yet run.
- Production deployment: not verified.
- Release: not published.
