# Known Issues and Refactor Backlog

## 2026092436 — Long-Term Retention Hardening

### Deployment verification still required

- Repository CI can verify the maintenance command, MySQL 5.7 migration and online-update package, but it cannot prove the production host has installed/enabled `zonoe-ipa-maintenance.timer`.
- After release/deployment, production should verify:
  - `systemctl is-enabled zonoe-ipa-maintenance.timer`
  - `systemctl is-active zonoe-ipa-maintenance.timer`
  - latest `ipa:maintenance` service exit status and output.

### Intentional retention behavior

- Dylib Device Keys are unlimited in count while active/recent; only keys unused for 365 days are removed.
- Missing IPA assets with manual category bindings are never auto-deleted.
- Retention runs are intentionally bounded. A very large historic backlog may require multiple daily runs to drain rather than one disruptive giant transaction.
- Audit-log retention defaults to 365 days; operators can override via environment configuration.

### Compatibility boundaries

- Authorization remains `UDID + Dylib Key`.
- BundleID is not part of Device Key enrollment identity.
- Existing 2435 multi-Key behavior is unchanged.
- Protocol v3 canonical signing fields are unchanged.
- Retention changes do not alter normal App authorization decisions.
