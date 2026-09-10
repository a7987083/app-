<?php

require __DIR__ . '/../application/common/library/SourceConfigRepository.php';

use app\common\library\SourceConfigRepository;

function expectSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$rows = [
    ['name' => 'name', 'value' => 'zonoe'],
    ['name' => 'payURL', 'value' => 'https://pay.example'],
    ['name' => 'opencry', 'value' => '1'],
    ['name' => 'dylib-on', 'value' => '0'],
    ['name' => 'nullable', 'value' => null],
    ['value' => 'ignored'],
];

expectSame([
    'name' => 'zonoe',
    'payURL' => 'https://pay.example',
    'opencry' => '1',
    'dylib-on' => '0',
    'nullable' => null,
], SourceConfigRepository::mapRows($rows), 'mapRows preserves raw config values');

expectSame([
    'name' => 'zonoe',
    'pay' => 'https://pay.example',
    'on' => '0',
], SourceConfigRepository::project($rows, [
    'name' => 'name',
    'payURL' => 'pay',
    'dylib-on' => 'on',
    'missing' => 'missing',
]), 'project preserves legacy omission for missing keys');

echo "OK source_config_repository_test\n";
