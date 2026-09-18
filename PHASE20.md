# Phase 20 — IPA Management Center

Baseline: `source-v2026091809` / `21307ba5ec9b65ce0b5f0643555ccc7f7bec0ad9`.

## Stages

- 20.0 UI/menu/auth skeleton
- 20.1 persistence, task, idempotency and audit foundations
- 20.2 OpenList discovery and incremental scan
- 20.3 HTTP Range IPA parser and metadata/icon extraction
- 20.4 persistent IPA ↔ category binding
- 20.5 metadata-to-database write-back templates
- 20.6 governance preview/apply/verify workflow
- 20.7 production hardening: batch operations, retries, interrupted recovery, retention and Range metrics

## Safety invariants

1. Stable release/tag history is never rewritten.
2. No OpenList mutation or category write-back is introduced before its dedicated phase.
3. Every future mutating operation must have an idempotency key and audit entry.
4. Governance mutation must use preview → confirm → execute → verify; stale plans are rejected.
5. Binding never uses fuzzy app-name matching as an authoritative key.
6. Parser cache identity is content-oriented (MD5/size) and parser-versioned.
7. High-risk database/OpenList actions use separate permission nodes.
8. Interrupted background work must be observable and recoverable.
