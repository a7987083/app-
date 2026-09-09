# Known issues

- Core lookup columns in `fa_black`, `fa_monitor`, and `fa_kami` have no dedicated indexes in the bundled install schema.
- Unlock-code activation is not atomic and can race under concurrent requests.
- The AppStore remote HTTP helper disables TLS certificate verification and has no explicit timeout/error contract.
- `Index::index()` performs one child-category query per parent category (N+1).
- `Category::index()` performs a broad daily counter reset update during an admin page request.
- `App::list()` and several other controllers access PHP superglobals directly instead of consistently using ThinkPHP request objects.
- `application/index/controller/App-mb.php` is a stale duplicate/backup implementation and should not be treated as an active controller source of truth.
- The root project has no `composer.lock` while `vendor/` and framework sources are committed, reducing reproducibility and making dependency ownership unclear.
- Legacy schema field names (`bt1a`, `bt1b`, `bt2a`, `bt2b`) hide domain meaning and make incorrect mappings easier.
