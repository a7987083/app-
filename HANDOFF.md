# Software Source Development Handoff

## Current stable baseline

- Repository: `a7987083/app-`
- Stable version: `2026091809`
- Stable tag: `source-v2026091809`
- Stable commit: `21307ba5ec9b65ce0b5f0643555ccc7f7bec0ad9`
- Status: production rollback baseline / refactor starting point

## Rollback decision

Development after `2026091809` is retired from the active baseline.

The post-1809 line introduced Phase 20 / IPA Management Center functionality and related persistence, scanner, parser, worker, binding, governance, write-back and deployment/runtime changes. Production has been rolled back to `2026091809`, so future work must not continue from the Phase 20 line.

The goal is to refactor from the last pre-Phase-20 stable release, preserving existing software-source behavior before any IPA management capability is redesigned.

See `ROLLBACK_2026091809.md` for the cleanup rationale and retention policy.

## Handoff rule

Do not treat branches, releases, tags or generated assets newer than `2026091809` as valid development baselines. Any useful Phase 20 implementation detail must be reviewed and reintroduced deliberately after the refactor, not copied forward wholesale.
