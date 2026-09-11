<?php
require_once dirname(__DIR__) . '/application/common/library/SourceAppRecord.php';

use app\common\library\SourceAppRecord;

function sourceAppAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL source_app_record_test: {$message}\n");
        exit(1);
    }
}

$row = [
    'name' => 'Demo', 'nickname' => '1.2.3', 'keywords' => 'desc',
    'bt1a' => 'https://example.test/a.ipa', 'bt1b' => 'abcdef',
    'bt2a' => 123, 'bt2b' => '1', 'flag' => '0', 'image' => 'icon',
    'updatetime' => 123456,
];
sourceAppAssert(SourceAppRecord::value($row, 'version') === '1.2.3', 'version alias');
sourceAppAssert(SourceAppRecord::value($row, 'download_url') === 'https://example.test/a.ipa', 'download alias');
sourceAppAssert(SourceAppRecord::value($row, 'paid') === '1', 'paid alias');
$columns = SourceAppRecord::publicSourceColumns();
foreach (array('nickname', 'keywords', 'bt1a', 'bt1b', 'bt2a', 'bt2b') as $column) {
    sourceAppAssert(in_array($column, $columns, true), 'missing legacy physical column ' . $column);
}

echo "OK source_app_record_test\n";
