# Phase 7 — Category Admin Performance

## Goal
Reduce browser memory and backend work for installations with about 5,000 apps without changing `/appstore`, `appstore`, `appstore_v2`, card, blacklist or trace-monitor protocol behavior.

## Implemented
- Category admin list now uses BootstrapTable server-side pagination.
- Default page size: 1000 rows.
- Available page sizes: 200 / 500 / 1000.
- Quick search keeps the existing search box but queries the full database by application `name`.
- Type tabs are evaluated by the server and return to page 1 on every type change.
- Default hidden columns: software description (`keywords`), remark (`beizhu`), app icon (`image`), weight (`weigh`). They remain available in the column chooser.
- `Category::_initialize()` no longer loads every category and builds the full Tree for index requests. The full parent tree is built only for add/edit where the historical parent selector still needs it.
- List requests select only table/column-chooser fields: `id,type,name,nickname,keywords,bt2b,beizhu,image,weigh,status`.
- The historical `cs/cstime` write-on-read reset remains unchanged in this phase.

## Compatibility boundary
No changes were made to:
- `/appstore` payload schema.
- `appstore` or `appstore_v2` encryption.
- download URL / icon URL protocol fields.
- card activation or lock semantics.
- blacklist enforcement.
- trace monitor behavior.
- add/edit field normalization.

## Automated validation
`tests/category_listing_contract_test.php` locks the following contracts:
- server pagination is enabled;
- default page size is 1000;
- only 200/500/1000 page sizes are accepted;
- quick search is server-side and no longer keeps `allRows` / local full-list filtering;
- type tab requests include the selected type and reset to page 1;
- four heavy/secondary columns are hidden by default;
- index no longer iterates the full in-memory category tree;
- list SQL uses limit/offset and a focused field list.

GitHub Actions Run `34539342456` passed PHP 7.0, 8.2 and 8.4.

## Real-site smoke test
1. Open Project Management with the real ~4,700 app database.
2. Confirm the first response shows total count but renders at most 1000 rows.
3. Navigate to page 2 and confirm a different row range is returned.
4. Search for an app known to be outside page 1; confirm it is found.
5. Switch type tabs; confirm filtering is correct and pagination returns to page 1.
6. Confirm description, remark, icon and weight are hidden by default.
7. Re-enable each hidden column from the column chooser and confirm values render correctly.
8. Verify add, edit, delete, batch status changes and drag-sort controls still work for the current page.
9. Compare browser memory with the previous full-list page.

## Rollback
Phase 6 remains the compatibility rollback baseline. `main` is unchanged.
