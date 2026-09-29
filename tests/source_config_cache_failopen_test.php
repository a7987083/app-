<?php

namespace think {
    class Cache
    {
        public static $throwGet = true;
        public static $throwSet = true;
        public static $throwRemove = true;

        public static function get($key)
        {
            if (self::$throwGet) {
                throw new \RuntimeException('cache read unavailable');
            }
            return null;
        }

        public static function set($key, $value, $ttl = null)
        {
            if (self::$throwSet) {
                throw new \RuntimeException('cache write unavailable');
            }
            return true;
        }

        public static function rm($key)
        {
            if (self::$throwRemove) {
                throw new \RuntimeException('cache remove unavailable');
            }
            return true;
        }
    }

    class Db
    {
        public static $rows = [];

        public static function name($name)
        {
            if ($name !== 'config') {
                throw new \RuntimeException('unexpected table: ' . $name);
            }
            return new SourceConfigQueryStub();
        }
    }

    class SourceConfigQueryStub
    {
        public function select()
        {
            return Db::$rows;
        }
    }
}

namespace {
    require __DIR__ . '/../application/common/library/SourceConfigRepository.php';

    use app\common\library\SourceConfigRepository;

    function sourceConfigFailOpenAssert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL source_config_cache_failopen_test: {$message}\n");
            exit(1);
        }
    }

    \think\Db::$rows = [
        ['name' => 'name', 'value' => 'zonoe'],
        ['name' => 'opencry', 'value' => '2'],
    ];

    $rows = SourceConfigRepository::rows(true);
    sourceConfigFailOpenAssert($rows === \think\Db::$rows, 'cache read/write outage must fall back to DB rows');

    $values = SourceConfigRepository::mapRows($rows);
    sourceConfigFailOpenAssert(isset($values['name']) && $values['name'] === 'zonoe', 'normal config mapping changed');
    sourceConfigFailOpenAssert(isset($values['opencry']) && $values['opencry'] === '1', 'legacy opencry v2 normalization changed');

    // Cache invalidation is best-effort. An unavailable cache backend must not
    // turn a successful admin write into an application-level exception.
    SourceConfigRepository::forget();

    echo "OK source_config_cache_failopen_test\n";
}
