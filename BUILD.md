# Build / validation

This legacy PHP application has no compilation step.

Phase-1 refactor validation:

```bash
php -l application/index/service/AppSourceBuilder.php
php -l application/index/controller/App.php
php -l tests/AppSourceBuilderRegressionTest.php
php tests/AppSourceBuilderRegressionTest.php
```

GitHub Actions additionally runs the same pure regression suite in PHP 5.6, 7.4, and 8.2 Docker images.

A full runtime verification still requires a configured MySQL database and the external encryption service used when `opencry=1`.
