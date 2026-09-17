<?php

namespace think {
    class Db
    {
        public static function query($sql, $bind = [])
        {
            if (stripos((string)$sql, 'SHOW COLUMNS') === 0) {
                return [];
            }
            throw new \RuntimeException('unexpected query in schema compatibility test');
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/application/common/library/SourceAppRecord.php';
    require_once dirname(__DIR__) . '/application/common/library/AppStorePayload.php';

    use app\common\library\AppStorePayload;
    use app\common\library\SourceAppRecord;

    function p177Assert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL phase17_7_appstore_schema_compat_test: {$message}\n");
            exit(1);
        }
    }

    $columns = SourceAppRecord::publicSourceColumns();
    p177Assert(!in_array('renewal_entry', $columns, true), 'runtime source query must omit missing renewal_entry');
    foreach (array('id', 'name', 'nickname', 'bt1a', 'bt2b', 'image', 'updatetime') as $column) {
        p177Assert(in_array($column, $columns, true), 'legacy source column missing: ' . $column);
    }

    $legacyRow = [
        'id' => 1,
        'type' => 'default',
        'name' => 'Legacy App',
        'nickname' => '1.0',
        'keywords' => 'legacy',
        'bt1a' => 'https://example.test/legacy.ipa',
        'bt1b' => 'ffffff',
        'bt2a' => 123,
        'bt2b' => '0',
        'flag' => '0',
        'image' => 'https://example.test/icon.png',
        'updatetime' => 1700000000,
    ];
    $apps = AppStorePayload::apps([$legacyRow], 'guest', false);
    p177Assert(isset($apps[0]['name']) && $apps[0]['name'] === 'Legacy App', 'legacy row did not map to source payload');
    p177Assert(isset($apps[0]['downloadURL']) && $apps[0]['downloadURL'] === 'https://example.test/legacy.ipa', 'legacy row download mapping changed');

    $withRenewal = SourceAppRecord::publicSourceColumns(true);
    p177Assert(in_array('renewal_entry', $withRenewal, true), 'renewal feature must remain available when schema has the column');

    fwrite(STDOUT, "OK phase17_7_appstore_schema_compat_test missing_column=passed legacy_payload=passed renewal_feature=preserved\n");
}
