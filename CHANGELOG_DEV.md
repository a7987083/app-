# Development changelog

## 2026-09-09 - Phase 1 AppStore refactor

- Added `application/index/service/AppSourceBuilder.php`.
- Removed duplicated catalog/config/payload construction branches from `App::list()` and routed both through one `renderAppStore()` path.
- Preserved all three historical download-lock semantics: no license, active license, expired license.
- Added `tests/AppSourceBuilderRegressionTest.php` to compare the new pure builder against the pre-refactor transformation logic.
- Added GitHub Actions regression checks for PHP 5.6, 7.4, and 8.2.
- Added architecture/refactor/build/known-issues documentation.
