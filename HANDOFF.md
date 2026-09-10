# Software Source Development Handoff

## Repository / baseline
- Repository: `a7987083/app-`
- Development branch: `dev/software-source-v1`
- Stable branch: `main`
- Stable baseline commit: `598235962ea328c6558fe4935fe19ba552c1490d`
- `main` remains untouched.

## Architecture
- ThinkPHP 5.0.24 / FastAdmin-style application.
- Public source route: `/appstore` -> `application/index/controller/App.php::list()`.
- Runtime source tables: `fa_config`, `fa_category`, `fa_kami`, `fa_black`, `fa_monitor`.
- Public source mapping: `application/common/library/AppStorePayload.php`.
- Shared config cache: `application/common/library/SourceConfigRepository.php`.
- Blacklist semantics: `application/common/library/BlacklistPolicy.php`.
- Trace semantics: `application/common/library/TraceMonitorPolicy.php`.
- Daily Category stats: `application/common/library/CategoryDailyStat.php`.
- Card code generation: `application/common/library/CardCodeGenerator.php`.

## Protocol compatibility boundary
The public source contract remains unchanged. Source keys remain `name`, `message`, `identifier`, `sourceURL`, `sourceicon`, `payURL`, `unlockURL`, `apps`; app keys remain `name`, `type`, `version`, `versionDate`, `versionDescription`, `lock`, `downloadURL`, `isLanZouCloud`, `iconURL`, `tintColor`, `size`.

`APPSTORE: v2` still selects `appstore_v2`; other values still use `appstore`. Plain/encrypted UDID/Time behavior, guest/licensed lock semantics, multiline description behavior and external encryption endpoints remain unchanged.

## Phase history
### Phase 1-3
- Extracted AppStore mapping and equivalence tests.
- Fixed dylib null access and homepage child-category N+1.
- Centralized Category write/display rules.
- Made Monitor blacklist move and Kami generation transactional.
- Added `SourceConfigRepository` 60-second shared config cache with invalidation.
- CI matrix expanded to PHP 7.0 / 8.2 / 8.4.

### Phase 4-6
- Fixed BaoTa release DB placeholder packaging (`BT_DB_NAME`, `BT_DB_USERNAME`, `BT_DB_PASSWORD`).
- Added root `nginx.rewrite`; real BaoTa deployment/blacklist behavior was user-reported successful.
- Added `BlacklistPolicy`, complete NOT NULL blacklist writes, expiration and first-hit `usetime` behavior.
- Added `TraceMonitorPolicy`, clarified 添加者/破解者 monitor UI labels, test/probe tooling and removed inherited 2022 monitor observations from fresh release seed.

### Phase 7 — 5000-App admin performance
- Category admin switched from full client-side list to true server-side pagination.
- Default page size `1000`; choices `200 / 500 / 1000`.
- Quick search queries the full database by application name.
- Type tabs filter server-side and reset page number.
- `软件说明 / 备注 / 应用图标 / 权重` are hidden by default but remain available through the column chooser.
- List AJAX no longer constructs the full Category Tree; parent tree is deferred to add/edit.
- List query returns only table fields.
- User subsequently showed a real page with `4713` total rows and `1000` rows per page, confirming the paging path is active.

### Phase 8 — admin, statistics and maintenance bundle
#### Category UI
- Native BootstrapTable `paginationVAlign: 'both'` shows paging controls at the top and bottom.
- Successful Category add closes the add layer without triggering the parent 1000-row table refresh; manual refresh remains available.
- New Category/App form defaults `是否付费` to `付费`; edit continues to use stored values.

#### Category `cs/cstime`
- Removed the write-on-read reset from `Category::index()`; simply opening/refreshing the admin list no longer updates every stale Category row.
- Added `CategoryDailyStat` and changed the daily identity from `date('d')` to integer `YYYYMMDD` (`20260911`), which fits the existing `int(11)` column and fixes same-day-number cross-month collisions.
- Existing legacy `cstime` values `1..31` require no migration: the next hit is treated as a new day and lazily rewrites the row to `YYYYMMDD`.
- Same-day semantics remain: first hit of a new date sets `cs=1`; later hits increment `cs`.
- Recording uses a transaction plus row lock to avoid concurrent lost increments/resets.
- Both `Index.php` and the retained `Index2.php` compatibility path call the same helper.

#### Card maintenance
- Visible card format remains uppercase prefix + 12 uppercase hexadecimal characters.
- Generator changed from MD5/time/`rand()` to `random_bytes(6)`.
- A generated batch is unique in memory and checked against existing `fa_kami.kami`; collisions are regenerated with a bounded retry.
- Card type must be one of day/week/month/quarter/year IDs `1..5`.
- Each insert result is checked; inserts remain one-by-one in the existing transaction to avoid changing failure semantics.
- Empty `addtime/usetime/endtime` model values now normalize to `0`, matching the existing NOT NULL schema.
- No DB unique index is added in this phase because existing production duplicate history has not been migrated/audited.

#### Blacklist maintenance
- Manual admin add refuses another active blacklist row for the same UDID.
- An expired-only history does not prevent creating a new active row.
- Existing duplicate history is not destructively rewritten.
- Expired rows remain as audit history and display `已过期`; `endtime=0` remains `永久`, `usetime=0` remains `未使用`.

#### App-mb.php / Index2.php cleanup boundary
- Static repository audit found no application route/reference to `App-mb.php` or `Index2.php`; `application/route.php` only maps `/appstore` to `index/App/list` and `/log` to `index/App/log`.
- **The two files are intentionally still present.** The requested rule was "confirm no production access before removal", and this chat has no SSH/access to BaoTa `/www/wwwlogs`.
- Bundled guard:
  ```bash
  bash tools/legacy_controller_access_audit.sh /www/wwwlogs
  ```
- To remove only after a zero-hit audit:
  ```bash
  bash tools/legacy_controller_access_audit.sh \
    --delete /www/wwwroot/app3.zonoeios.xyz \
    /www/wwwlogs
  ```
- A matching access record exits `2` and refuses deletion; missing logs exit `3` and also refuse deletion.

## CI / tests
Phase 8 code CI run `34542868818` passed the full PHP `7.0 / 8.2 / 8.4` matrix. Coverage now includes prior AppStore/config/trace/blacklist/deployment tests plus:
- `category_listing_contract_test.php`
- `category_daily_stat_test.php`
- `category_statistics_contract_test.php`
- `card_code_generator_test.php`
- `card_maintenance_contract_test.php`
- `blacklist_maintenance_test.php`
- `legacy_controller_contract_test.php`
- `legacy_controller_audit_test.sh`

## Performance finding retained
Observed on the server:
- `opencry=0`: TTFB `0.102178s`, total `0.110784s`.
- `opencry=1`: TTFB `10.464952s`, total `10.476304s`.
The dominant public `/appstore` latency is the existing whole-payload external encryption call. The user chose to keep current `appstore/appstore_v2` encryption behavior, so Phase 8 does not change it.

## Validation still required
- Real Phase 8 BaoTa deployment / admin smoke test.
- Verify top+bottom pager, add-without-parent-refresh and paid default.
- Exercise one `cs/cstime` hit across a new date condition if practical.
- Generate and activate test cards.
- Test duplicate active blacklist add and expired display.
- Run the production access-log audit before deleting `App-mb.php` / `Index2.php`.
- Production regression is not claimed until these live checks are done.

## Stability rules
1. Keep `main` unchanged until explicitly promoted.
2. Do not change public source protocol/encryption semantics without a dedicated compatibility phase.
3. Keep schema migrations separate from compatibility maintenance unless required and tested.
4. BaoTa ZIP must have root `auto_install.json`, `import.sql`, `nginx.rewrite`; `application/database.php` must contain only the `BT_DB_*` placeholders before installation.
5. Fresh release SQL must not preload runtime `fa_monitor` observations.
6. Never delete legacy controllers solely from static analysis; production access logs are the deletion gate.
