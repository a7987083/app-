<?php

function p1931LoadFail($message)
{
    fwrite(STDERR, "FAIL phase19_3_1_load_result_gate: {$message}\n");
    exit(1);
}

function p1931LoadAssert($condition, $message)
{
    if (!$condition) {
        p1931LoadFail($message);
    }
}

$file = isset($argv[1]) ? $argv[1] : '';
p1931LoadAssert($file !== '' && is_file($file), 'result TSV missing');

$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
p1931LoadAssert(is_array($lines) && count($lines) >= 10, 'not enough benchmark rows');

$header = str_getcsv(array_shift($lines), "\t");
$rows = [];
foreach ($lines as $line) {
    $values = str_getcsv($line, "\t");
    if (count($values) !== count($header)) {
        p1931LoadFail('malformed TSV row: ' . $line);
    }
    $row = array_combine($header, $values);
    $rows[$row['scenario']] = $row;
}

$required = [
    '5000_plain_guest_c16',
    '10000_plain_guest_c16',
    '20000_plain_guest_c16',
    '20000_plain_guest_c32',
    '20000_plain_licensed_c16',
    '5000_encrypted_guest_c8',
    '10000_encrypted_guest_c8',
    '20000_encrypted_guest_c8',
    '20000_encrypted_licensed_c8',
];

foreach ($required as $scenario) {
    p1931LoadAssert(isset($rows[$scenario]), 'missing scenario ' . $scenario);
    $row = $rows[$scenario];
    p1931LoadAssert((int)$row['errors'] === 0, $scenario . ' reported HTTP/socket errors');
    p1931LoadAssert((int)$row['requests'] >= (int)$row['concurrency'], $scenario . ' completed too few requests');
    p1931LoadAssert((float)$row['rps'] > 0, $scenario . ' has invalid RPS');
    p1931LoadAssert((float)$row['p95_ms'] > 0 && (float)$row['p99_ms'] > 0, $scenario . ' latency percentiles missing');
    p1931LoadAssert((int)$row['body_bytes'] > 1000, $scenario . ' body unexpectedly small');
    p1931LoadAssert((float)$row['cold_ms'] > 0 && (float)$row['hot_ms'] > 0, $scenario . ' cold/hot timing missing');
}

p1931LoadAssert(
    (int)$rows['20000_plain_guest_c16']['body_bytes'] > (int)$rows['10000_plain_guest_c16']['body_bytes'],
    '20k plain payload must be larger than 10k'
);
p1931LoadAssert(
    (int)$rows['10000_plain_guest_c16']['body_bytes'] > (int)$rows['5000_plain_guest_c16']['body_bytes'],
    '10k plain payload must be larger than 5k'
);
p1931LoadAssert(
    (int)$rows['20000_encrypted_guest_c8']['body_bytes'] > (int)$rows['10000_encrypted_guest_c8']['body_bytes'],
    '20k encrypted payload must be larger than 10k'
);

$summary = [
    'plain_20k_rps_c16' => (float)$rows['20000_plain_guest_c16']['rps'],
    'plain_20k_p95_ms_c16' => (float)$rows['20000_plain_guest_c16']['p95_ms'],
    'plain_20k_p99_ms_c16' => (float)$rows['20000_plain_guest_c16']['p99_ms'],
    'encrypted_20k_rps_c8' => (float)$rows['20000_encrypted_guest_c8']['rps'],
    'encrypted_20k_p95_ms_c8' => (float)$rows['20000_encrypted_guest_c8']['p95_ms'],
    'encrypted_20k_p99_ms_c8' => (float)$rows['20000_encrypted_guest_c8']['p99_ms'],
];

fwrite(STDOUT, 'OK phase19_3_1_load_result_gate ' . json_encode($summary, JSON_UNESCAPED_SLASHES) . "\n");
