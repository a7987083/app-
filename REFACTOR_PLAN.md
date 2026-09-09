# Refactor plan

## Phase 0 - Behavior baseline

- Lock down existing `/appstore` field order, lock/download rules, newline handling, and encryption input.
- Keep legacy edge behavior in tests before fixing it.

## Phase 1 - AppStore payload extraction (implemented)

- Extract pure catalog/config/payload transformation into `AppSourceBuilder`.
- Collapse the duplicated licensed/unlicensed catalog-building branches in `App.php`.
- Add regression tests that compare the new builder with a literal reproduction of the old transformation for no-license, active-license, and expired-license states.

## Phase 2 - Query/performance cleanup

- Add indexes for `fa_black.udid`, `fa_monitor.udid`, `fa_kami.udid`, and `fa_kami.kami` after checking production duplicates/cardinality.
- Replace `select()[0]` patterns with a single-row query where ordering semantics allow it.
- Remove the front-page category N+1 query by fetching children in one query and grouping in PHP.
- Stop updating all `fa_category` rows from the admin list page merely to reset daily counters; use date-aware reads or a scheduled/reset mechanism.

## Phase 3 - Authorization correctness

- Make card activation atomic (`UPDATE ... WHERE jh=0`) and verify affected rows to close the concurrent double-activation window.
- Validate supported `kmyp` values before calculating expiry.
- Centralize UDID normalization/validation instead of repeating length checks.
- Use transactions when a black-list move changes both `fa_black` and `fa_monitor`.

## Phase 4 - HTTP and error handling

- Replace the controller-owned `curl()` helper with a small HTTP client abstraction.
- Restore TLS certificate verification and add connect/read timeouts.
- Handle remote encryption failures explicitly instead of returning an unchecked false/empty payload.
- Replace `echo ...; die` branches with a single response boundary once compatibility fixtures cover exact client expectations.

## Phase 5 - Data model and dependency hygiene

- Introduce domain-named fields/models instead of overloaded `bt1a/bt1b/bt2a/bt2b` names, using a compatibility migration rather than a flag-day rename.
- Separate generated/runtime files from source control.
- Add a reproducible dependency lock/update policy; the repository currently vendors dependencies while lacking a root `composer.lock`.
- Plan framework/runtime upgrades separately from business refactoring because ThinkPHP/FastAdmin upgrades can change request, ORM, and template behavior.
