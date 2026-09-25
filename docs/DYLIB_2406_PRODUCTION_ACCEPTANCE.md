# Dylib 2406 Production Acceptance

> Status: development acceptance checklist. `source-v2026092406` must not be published until all P0 items below have real BaoTa/device evidence.

## 1. Preflight

- [ ] Confirm current production/staging backup exists before applying 2406 SQL.
- [ ] Record current application commit/version and database backup identifier.
- [ ] Confirm current stable release remains `source-v2026092405` until acceptance completes.
- [ ] Use a non-production/test Dylib key first when possible.

## 2. BaoTa / MySQL migration

Apply `release/sql/2026092406_dylib_runtime_access.sql` twice.

Expected:

- [ ] First run succeeds.
- [ ] Second run succeeds without duplicate-table/duplicate-row failure.
- [ ] `fa_ipa_app_identity` exists.
- [ ] `fa_dylib_runtime_config` exists and contains `id=1`.
- [ ] `fa_dylib_runtime_notice` exists.
- [ ] Existing `fa_dylib_app_binding` history is preserved.
- [ ] Existing 2405 Dylib registrations/versions/logs remain intact.

Release blocker: any destructive schema side effect or second-run failure.

## 3. Parsed App identity preparation

Choose at least two real Apps already present in the software source: App A and App B.

For each App:

- [ ] IPA status becomes `parsed`.
- [ ] Active `fa_ipa_category_binding` points to the correct `fa_category.id`.
- [ ] `fa_ipa_app_identity` contains the parsed main executable identity.
- [ ] Stored BundleID matches the parsed IPA.
- [ ] Stored executable matches `CFBundleExecutable`.
- [ ] Stored Mach-O UUID is non-empty.

Negative preparation check:

- [ ] An unparsed/stale asset does not remain authoritative for scope=3.

## 4. scope=2 — verification-only card

Device/UDID has only a valid `card_scope=2` card.

Expected verification result:

```text
access_level = basic
permissions.normal_menu = true
permissions.extra_menu = false
permissions.extra_features = false
```

Verify on device:

- [ ] App A normal menu works.
- [ ] App B normal menu works.
- [ ] Extra menu is hidden/disabled in both Apps.
- [ ] Independent paid entitlements such as cloud save are unchanged.

## 5. scope=3 — App-specific card

Bind the card only to App A (`fa_kami_app.app_id = App A category id`).

### App A

Expected:

```text
access_level = app_plus
permissions.normal_menu = true
permissions.extra_menu = true
permissions.extra_features = true
```

- [ ] App A receives `app_plus`.
- [ ] Response `app_identity.category_id` is App A's server-side category id.

### App B

With no fallback scope=2 card:

- [ ] App B does not receive `app_plus`.
- [ ] Expected result is block / not authorized for current App.

Release blocker: a scope=3 card unlocks a different App.

## 6. BundleID-only spoof negative test — P0

Use App B and change only its BundleID so it equals App A's BundleID. Do not replace App B executable/Mach-O identity.

Expected:

- [ ] Server does not resolve App B as App A.
- [ ] `app_plus` is not granted.
- [ ] Identity result is unknown/incomplete/mismatch rather than App A.

Release blocker: changing only BundleID grants App A's scope=3 privileges.

## 7. Same UDID with multiple cards

Give one UDID:

- valid scope=2 card
- valid scope=3 card bound only to App A

Expected:

- [ ] App A -> `app_plus`.
- [ ] App B -> `basic` rather than block.
- [ ] Expired/disabled cards do not participate.

Then add a valid scope=1 card:

- [ ] App A -> `global_plus`.
- [ ] App B -> `global_plus`.

## 8. scope=1 — full-source card

With only a valid scope=1 card:

Expected:

```text
access_level = global_plus
permissions.normal_menu = true
permissions.extra_menu = true
permissions.extra_features = true
```

- [ ] App A works with full Dylib features.
- [ ] App B works with full Dylib features.
- [ ] No per-App Dylib whitelist is required.

## 9. Game update detection

Prepare current device App version lower than the latest parsed/bound IPA version.

Expected:

- [ ] `app_update.available = true`.
- [ ] `current_version/current_build` are the running App values.
- [ ] `latest_version/latest_build` match the newest parsed asset selected by the server.
- [ ] Popup title/body/button labels match server configuration.
- [ ] Primary update button points to the expected software-source download URL.
- [ ] Same update revision is not shown repeatedly if the client suppression rule applies.

No-update control:

- [ ] When current version is equal/newer, `app_update.available = false`.

## 10. Runtime notice

Create one global notice and one App-specific notice.

Verify:

- [ ] Global notice reaches eligible Apps.
- [ ] App-specific notice only reaches the target App identity.
- [ ] `min_access_level` is respected.
- [ ] `starts_at/ends_at` are respected.
- [ ] `revision` changes allow a deliberate re-notification.
- [ ] Primary/secondary button labels/actions/URLs are rendered correctly.
- [ ] Notice content never changes card permission or independent entitlements.

## 11. Server / domain migration — P0

Configure at least two Bootstrap URLs and at least two API endpoints where practical.

### Normal discovery

- [ ] Dylib retrieves signed runtime config.
- [ ] Config signature validates.
- [ ] Valid config is stored as Last-Known-Good.

### API domain rotation

Change primary business API domain in runtime config without rebuilding the Dylib.

- [ ] Existing Dylib discovers the new endpoint.
- [ ] Verification, card permissions, update detection and notices continue working.

### Bootstrap failover

Break Bootstrap A while Bootstrap B remains available.

- [ ] Client continues through Bootstrap B.
- [ ] No Dylib rebuild is required.

### Last-Known-Good

Temporarily make Bootstrap endpoints unavailable while the previously discovered API endpoint remains reachable.

- [ ] Client uses cached signed config.
- [ ] Normal verify still succeeds.

## 12. Offline grace

After a successful online verification:

- [ ] Temporarily disconnect verification infrastructure within configured grace.
- [ ] Allowed offline behavior uses the last valid authorization token/cache.
- [ ] scope=3 cached authorization cannot be reused in a different App identity.
- [ ] After grace/token expiry, access fails according to policy rather than remaining permanently unlocked.

## 13. 2405 compatibility regression

- [ ] Existing v1 HMAC client still verifies using the original canonical order.
- [ ] Dylib enable/disable behavior remains fail-closed.
- [ ] Dylib version blocked/revoked rules still work.
- [ ] Dylib integrity/SHA rules still work.
- [ ] Historical verification logs remain readable.
- [ ] Historical `dylib_app_binding` rows remain stored even though 2406 runtime authorization no longer depends on them.

## 14. Release gate

All conditions must be true before preparing `source-v2026092406`:

- [ ] BaoTa migration verified.
- [ ] scope=2 device test passed.
- [ ] scope=3 matching App device test passed.
- [ ] scope=3 different-App device test passed.
- [ ] BundleID-only spoof negative test passed.
- [ ] scope=1 device test passed.
- [ ] Multi-card merge test passed.
- [ ] Game update notification passed.
- [ ] Runtime notice passed.
- [ ] API domain rotation passed.
- [ ] Bootstrap failover passed.
- [ ] Last-Known-Good passed.
- [ ] Offline grace/expiry passed.
- [ ] 2405 compatibility regression passed.

If any P0 item fails, keep PR #25 / 2406 in development state and do not create the release tag.
