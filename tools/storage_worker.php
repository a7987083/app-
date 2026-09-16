<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$action = isset($argv[1]) ? trim($argv[1]) : '';
$root = isset($argv[2]) ? rtrim($argv[2], '/\\') . DIRECTORY_SEPARATOR : '';
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "invalid root\n");
    exit(2);
}

$file = $root . 'application/common/library/update/FastStorageManager.php';
if (!is_file($file)) {
    fwrite(STDERR, "FastStorageManager missing\n");
    exit(2);
}
require_once $file;

$manager = new \app\common\library\update\FastStorageManager($root);
try {
    $ok = $manager->runWorker($action);
    exit($ok ? 0 : 1);
} catch (\Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
