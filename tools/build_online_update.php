<?php

$root = dirname(__DIR__);
$output = isset($argv[1]) && $argv[1] !== '' ? $argv[1] : $root . '/zonoe-online-update.zip';
$manifest = $root . '/release/online-update-files.txt';
$sqlDir = $root . '/release/sql';

function release_fail($message)
{
    fwrite(STDERR, "FAIL build_online_update: {$message}\n");
    exit(1);
}

function release_safe_path($relative)
{
    $relative = str_replace('\\', '/', trim($relative));
    if ($relative === '' || $relative[0] === '/' || preg_match('/^[A-Za-z]:\//', $relative)) {
        return false;
    }
    foreach (explode('/', $relative) as $part) {
        if ($part === '..' || $part === '') {
            return false;
        }
    }
    $protected = ['application/database.php', '.env', 'uploads', 'public/uploads', 'runtime'];
    foreach ($protected as $path) {
        if ($relative === $path || strpos($relative, $path . '/') === 0) {
            return false;
        }
    }
    return true;
}

if (!is_file($manifest)) {
    release_fail('release/online-update-files.txt missing');
}
if (!class_exists('ZipArchive')) {
    release_fail('ZipArchive extension missing');
}

$lines = file($manifest, FILE_IGNORE_NEW_LINES);
if (!is_array($lines)) {
    release_fail('cannot read update manifest');
}
$files = [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    if (!release_safe_path($line)) {
        release_fail('unsafe/protected manifest path: ' . $line);
    }
    $source = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $line);
    if (!is_file($source)) {
        release_fail('manifest file missing: ' . $line);
    }
    $files[] = $line;
}
if (!$files) {
    release_fail('update manifest is empty');
}

@unlink($output);
$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    release_fail('cannot create zip: ' . $output);
}
$zip->addEmptyDir('program');
foreach ($files as $relative) {
    $source = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!$zip->addFile($source, 'program/' . $relative)) {
        $zip->close();
        release_fail('cannot add file: ' . $relative);
    }
}

$sqlCount = 0;
if (is_dir($sqlDir)) {
    $sqlFiles = glob($sqlDir . '/*.sql');
    if (is_array($sqlFiles) && $sqlFiles) {
        sort($sqlFiles, SORT_STRING);
        $zip->addEmptyDir('mysql');
        foreach ($sqlFiles as $sql) {
            $name = basename($sql);
            if (!preg_match('/^[A-Za-z0-9._-]+\.sql$/', $name)) {
                $zip->close();
                release_fail('unsafe SQL filename: ' . $name);
            }
            if (!$zip->addFile($sql, 'mysql/' . $name)) {
                $zip->close();
                release_fail('cannot add SQL: ' . $name);
            }
            $sqlCount++;
        }
    }
}
$zip->close();

if (!is_file($output) || filesize($output) <= 0) {
    release_fail('zip output missing');
}
$sha = hash_file('sha256', $output);
$shaFile = $output . '.sha256';
if (file_put_contents($shaFile, $sha . '  ' . basename($output) . "\n") === false) {
    release_fail('cannot write sha256 file');
}

fwrite(STDOUT, "OK build_online_update files=" . count($files) . " sql={$sqlCount} sha256={$sha}\n");
