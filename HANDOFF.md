# Handoff

- Repository: `a7987083/app-`
- Stable baseline: `main` at `5c50a5dfb7f5eb725527f080ec5e850d8bbe0dfd`
- Refactor branch: `refactor/app-source-phase1`
- Current goal: reduce `/appstore` duplication without changing externally observable source behavior.
- Completed: architecture review, first AppStore transformation extraction, legacy-equivalence regression test.
- Do not change in Phase 1: lock semantics, JSON field names/order, `@@@` newline compatibility, encryption input format, card expiry durations, blacklist behavior.
- Next task: validate branch CI, then address query indexes/N+1 in a separate phase/commit.
- Runtime verification status: pure transformation regression validated locally; database-backed endpoint and remote encryption service not yet exercised end-to-end.
