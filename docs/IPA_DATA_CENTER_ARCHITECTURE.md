# IPA Data Center v1

Baseline: `source-v2026091809` / `21307ba5ec9b65ce0b5f0643555ccc7f7bec0ad9`

## Scope

- OpenList is the only IPA source in v1.
- Maximum design target: 100,000 IPA assets.
- OpenList file downloads are expected to support HTTP Range.
- Web UI is integrated into the existing FastAdmin admin module.
- MySQL compatibility target is 5.7.
- Worker runtime is PHP CLI.
- `fa_category` write-back is manual only and must use preview -> apply.
- Dylib verification client is Objective-C/Objective-C++ and may be injected into many games.
- Device identity uses UDID only.
- Default offline grace is 900 seconds (15 minutes), configurable by policy.

## Architecture

```text
FastAdmin UI
    |
    v
Admin Controller / Application Service
    |
    +--> MySQL 5.7
    |      - source / asset / scan job / scan item
    |      - binary index / category binding
    |      - dylib registry / version / app binding
    |      - device session / verify log / replay nonce
    |
    +--> OpenList Adapter
    |      - /api/fs/list
    |      - /api/fs/get
    |
    +--> PHP CLI Worker
           - directory discovery
           - IPA queue
           - range parser (next milestone)
           - binary index (next milestone)

Objective-C dylib
    |
    v
Dylib Verification API
    - timestamp window
    - nonce replay protection
    - UDID authorization
    - Bundle ID / dylib / version policy
    - short-lived session token
    - 900s default offline grace
```

## Non-goals / safety boundaries

- v1 does not automatically modify `fa_category`.
- v1 does not modify the 2026091809 Category controller behavior.
- v1 does not reuse Phase 20 implementation code.
- the stable baseline branch remains immutable.

## Initial delivery stages

1. Foundation: schema, source adapter, scan jobs, worker, FastAdmin overview.
2. IPA range parser: EOCD + central directory + selective entry fetch.
3. IPA metadata and Mach-O/dylib/framework indexing.
4. Manual category binding + preview/apply write-back.
5. Dylib verification server API and policy management.
6. Objective-C verification SDK, tests, CI and deployment runbook.

## Scale rules

- No full-table rendering for asset lists.
- Pagination is server-side.
- Worker claims bounded batches with short transactions.
- Jobs/items use explicit state transitions and retry counters.
- Assets are upserted by `(source_id, path_hash)`.
- Binary files are deduplicated by SHA-256 once parsing is enabled.
- Logs are retention-controlled; no unbounded per-device history queries.
