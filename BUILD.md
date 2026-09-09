# Build / validation

This legacy PHP application has no compilation step.

Phase-1 refactor validation:

```bash
php -l application/index/service/AppSourceBuilder.php
php -l application/index/controller/App.php
php -l tests/AppSourceBuilderRegressionTest.php
php tests/AppSourceBuilderRegressionTest.php
```

GitHub Actions run `34296168358` passed the legacy-equivalence suite on PHP 5.6, 7.4, and 8.2.

Important runtime-contract caveat: the repository declares PHP `>=5.6`, but the stable baseline controller uses `public function list()`. `list` is a reserved keyword for the PHP 5.6 parser, so both the stable baseline and the refactored controller fail PHP 5.6 parsing at that pre-existing method name. CI therefore:

- runs the newly extracted pure builder and equivalence test on PHP 5.6/7.4/8.2;
- lints the real controller on PHP 7.4/8.2;
- proves on PHP 5.6 that the baseline and current controller fail for the same `list` token, then temporarily renames only that method in test copies and verifies the remainder of both files parses.

A full runtime verification still requires a configured MySQL database and the external encryption service used when `opencry=1`.
