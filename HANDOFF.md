# Handoff

- Repository: `a7987083/app-`
- Stable branch: `main`
- Stable baseline commit: `5c50a5dfb7f5eb725527f080ec5e850d8bbe0dfd`
- Refactor branch: `refactor/app-source-phase1`
- Phase-1 implementation commit: `198d10030108ee5081b035c9d99480267154648f`
- Validated CI commit before documentation refresh: `ebc121bc02949bd1baf23f184c8b0a75c8a2ccd2`
- Green GitHub Actions run: `34296168358`
- Current goal: reduce `/appstore` duplication without changing externally observable source behavior.
- Completed: architecture review, AppStore transformation extraction, legacy-equivalence regression test, PHP 5.6/7.4/8.2 compatibility matrix for the extracted logic.
- Do not change casually: lock semantics, JSON field names/order, `@@@` newline compatibility, encryption input format, card expiry durations, blacklist behavior, public `/appstore` route.
- Pre-existing runtime mismatch: `composer.json` declares PHP >=5.6, but `App::list()` is not parseable by PHP 5.6 because `list` is reserved there. Do not rename the route/controller method as part of an unrelated cleanup; handle it as a compatibility migration with route/direct-URL regression coverage.
- Next task: Phase 2 should address indexes and the front-page N+1 query in separate commits, then Phase 3 should make card activation atomic.
- Runtime verification status: pure transformation regression passed; database-backed `/appstore` and live remote encryption service have not been exercised end-to-end.
