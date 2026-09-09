# Known issues

## P0 / security and reliability

- `public/config.php` contains non-empty plaintext database connection credentials in the source tree. Do not expose or copy these values; rotate them if they have ever been used outside a disposable environment, move deployment secrets out of source control, and add an ignore/template policy.
- `App::curl()` disables TLS peer verification for the remote encryption service and has no explicit connection/read timeout or checked error contract.
- Unlock-code activation reads `jh` and later updates by `id` without an atomic condition/transaction, leaving a concurrent double-activation window.

## P1 / performance and correctness

- Core lookup columns have no dedicated indexes in the bundled install schema: `fa_black.udid`, `fa_monitor.udid`, `fa_kami.udid`, and `fa_kami.kami` all rely on primary-key-only table definitions/indexes.
- `Index::index()` performs one child-category query per parent category (N+1).
- `Category::index()` resets daily counters as a side effect of opening an admin page and stores only day-of-month in `cstime`; the same day number in another month can be mistaken for the same day.
- `/appstore` performs multiple separate reads from `fa_config` on each request (`openblack`, `openblack2`, `opencry`, then source metadata).
- Request-body parsing assumes JSON contains `value`, and the monitor payload assumes both `udid1|udid2` segments exist; malformed input can generate warnings/noisy logs.
- Card generation inserts rows one-by-one and uses `rand()` plus deterministic MD5 slicing rather than a cryptographically strong token source.

## P2 / maintainability

- `application/index/controller/App-mb.php` is a stale duplicate/backup implementation and should not be treated as an active controller source of truth.
- `App::list()` and several controllers access PHP superglobals directly instead of consistently using ThinkPHP request objects.
- Legacy schema field names (`bt1a`, `bt1b`, `bt2a`, `bt2b`) hide domain meaning and make incorrect mappings easier.
- The root project has no `composer.lock` while `vendor/` and framework sources are committed, reducing reproducibility and making dependency ownership unclear.
- The root `composer.json` says PHP `>=5.6`, while the existing `App::list()` method name is not parseable by PHP 5.6; the declared runtime contract and actual source are inconsistent.
