# Software Source Development Handoff

## Stable baseline

- Repository: `a7987083/app-`
- Stable release: `source-v2026092435`
- Stable target: `bda2656a699e93162ea38514f21e5027fc92ece9`

## Current development

- Version target: `2026092436`
- Branch: `work/2026092436-retention-step1`
- Focus: long-term database and disk retention hardening.

## Retention policy

- Dylib challenge: 1 day.
- Dylib session: expired + 1 day.
- Dylib verify log: 30 days default.
- API request log: 30 days default.
- Device Key: 365 days since last use.
- IPA parse attempt: 30 days.
- Terminal scan item: 7 days.
- Terminal scan job: 30 days.
- Authorization / transfer / admin / source-change audit logs: 365 days.
- Runtime logs: 30 days.
- Update status/history/backup: 14/90/30 days.
- Missing IPA asset: 365 days, only if no manual category binding.

## Critical fix

2430 dropped `fa_dylib_nonce`, while the old maintenance command still attempted to delete from it before every other cleanup. 2436 removes that call and cleans `fa_dylib_auth_challenge` instead. This restores the maintenance path after 2430+ migrations.

## Safety

- Database deletion is bounded to avoid huge transactions.
- Scan cleanup is cursor-safe.
- Missing assets with operator-owned category bindings are never auto-deleted.
- Online update contains the maintenance service/timer files, but production still requires the systemd unit to be installed and enabled with host privileges.

## Verification status

- Modified: yes.
- Committed: yes.
- Dedicated PHP/MySQL/package CI #28: SUCCESS.
- Full Release Gate: not run.
- Released: no.
- Production timer status: not verified.
