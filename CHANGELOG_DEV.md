# Development changelog

## 2026-09-09 - Phase 1 AppStore refactor

- Added `application/index/service/AppSourceBuilder.php`.
- Removed duplicated catalog/config/payload construction branches from `App::list()` and routed both through one `renderAppStore()` path.
- Preserved all three historical download-lock semantics: no license, active license, expired license.
- Added `tests/AppSourceBuilderRegressionTest.php` to compare the new pure builder against the pre-refactor transformation logic.
- Added GitHub Actions regression checks for PHP 5.6, 7.4, and 8.2.
- CI run `34296168358` passed all jobs after distinguishing the pre-existing PHP 5.6 `App::list()` reserved-word parser conflict from the new extracted code.
- Added architecture/refactor/build/known-issues/handoff documentation.
- No database schema, card expiry rule, blacklist rule, route, encryption request format, or public JSON field was intentionally changed in Phase 1.
