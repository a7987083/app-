# Phase 20 — IPA Management Center

Baseline: `source-v2026091809` / `21307ba5ec9b65ce0b5f0643555ccc7f7bec0ad9`.

## Stages

- 20.0 UI/menu/auth skeleton — implemented on feature branch
- 20.1 persistence, task, idempotency and audit foundations — implemented on feature branch
- 20.2 OpenList discovery and incremental scan — implemented on feature branch
- 20.3 HTTP Range IPA parser and metadata/icon extraction
- 20.4 persistent IPA ↔ category binding
- 20.5 metadata-to-database write-back templates
- 20.6 governance preview/apply/verify workflow
- 20.7 production hardening: batch operations, retries, interrupted recovery, retention and Range metrics

## Phase 20.1 persistence

Core tables:

- `fa_ipa_metadata`: parser-versioned raw + normalized metadata and content identity.
- `fa_ipa_binding`: strict one-to-one current binding between `fa_category` and metadata.
- `fa_ipa_scan_task` / `fa_ipa_scan_task_item`: checkpointable background-work state.
- `fa_ipa_governance_issue`: detected anomaly, ignore window and repair-plan hash.
- `fa_ipa_operation_log`: audit trail plus unique idempotency key for mutations.
- `fa_ipa_writeback_rule`: versioned global write-back template rules.

`IpaFoundation` centralizes remote-path identity, canonical plan hashes, idempotency keys, parser version, task states and field confidence levels.

## Phase 20.2 OpenList discovery

- `fa_ipa_source` stores one encrypted-token OpenList source plus scan/schedule settings.
- `IpaOpenListClient` reads `/api/fs/list` only; no IPA payload bytes are downloaded.
- `IpaInventoryCache` atomically caches safe directory inventory for the configured TTL; force-refresh scans bypass it.
- `IpaRemoteFile` normalizes path identity and selects the strongest cheap provider fingerprint: MD5 → ETag → size+mtime.
- `IpaScanPlanner` classifies remote inventory into new / changed / unchanged / missing without database side effects.
- `IpaScanService` writes only Phase20 tables. New/changed files enter `parse_state=pending` and `parser_pending` task items for Phase 20.3.
- CLI worker: `php think ipa:scan --task=<id>`, `--pending`, or `--schedule`.
- Manual admin scan creates a task and attempts a detached CLI worker; if process spawning is unavailable, cron/CLI can consume queued tasks.
- Existing `fa_category` rows are not read or written by Phase 20.2.

## Safety invariants

1. Stable release/tag history is never rewritten.
2. No OpenList mutation or category write-back is introduced before its dedicated phase.
3. Every future mutating operation must have an idempotency key and audit entry.
4. Governance mutation must use preview → confirm → execute → verify; stale plans are rejected.
5. Binding never uses fuzzy app-name matching as an authoritative key.
6. Parser cache identity is content-oriented (MD5/size) and parser-versioned.
7. High-risk database/OpenList actions use separate permission nodes.
8. Interrupted background work must be observable and recoverable.
