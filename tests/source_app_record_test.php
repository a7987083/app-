<?php

namespace think {
    class Db
    {
        public static function query($sql, $bind = [])
        {
            if (stripos((string)$sql, 'SHOW COLUMNS') === 0) {
                return [];
            }
            return [];
        }
    }
}

namespace {
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
        'bt2a' => 123, 'bt2b' => '1', 'renewal_entry' => '1', 'flag' => '0', 'image' => 'icon',
        'updatetime' => 123456,
    ];
    sourceAppAssert(SourceAppRecord::value($row, 'version') === '1.2.3', 'version alias');
    sourceAppAssert(SourceAppRecord::value($row, 'download_url') === 'https://example.test/a.ipa', 'download alias');
    sourceAppAssert(SourceAppRecord::value($row, 'paid') === '1', 'paid alias');
    sourceAppAssert(SourceAppRecord::value($row, 'renewal_entry') === '1', 'renewal entry alias');

    // Runtime path: schema probe says renewal_entry is absent, so /appstore must
    // not select the missing column.
    $runtimeColumns = SourceAppRecord::publicSourceColumns();
    sourceAppAssert(!in_array('renewal_entry', $runtimeColumns, true), 'runtime source columns must omit missing renewal_entry');

    $columnsWithRenewal = SourceAppRecord::publicSourceColumns(true);
    foreach (array('nickname', 'keywords', 'bt1a', 'bt1b', 'bt2a', 'bt2b', 'renewal_entry') as $column) {
        sourceAppAssert(in_array($column, $columnsWithRenewal, true), 'missing source physical column ' . $column);
    }

    $columnsLegacy = SourceAppRecord::publicSourceColumns(false);
    sourceAppAssert(!in_array('renewal_entry', $columnsLegacy, true), 'legacy schema must omit renewal_entry');

    $compatible = SourceAppRecord::publicSourceColumnsForSchema(array(
        'id', 'type', 'name', 'nickname', 'keywords', 'bt1a', 'bt1b', 'bt2a', 'bt2b',
        'flag', 'image', 'updatetime', 'weigh', 'status'
    ));
    sourceAppAssert(!in_array('renewal_entry', $compatible, true), 'schema-compatible columns must omit missing renewal_entry');
    sourceAppAssert(in_array('bt2b', $compatible, true), 'schema-compatible columns lost legacy field');

    echo "OK source_app_record_test runtime_missing_column=passed renewal_entry=optional schema_compat=passed\n";
}
