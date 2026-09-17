<?php

namespace think {
    class Cache
    {
        public static $getThrows = false;
        public static $setThrows = false;
        public static $rmThrows = false;
        public static $cachedValue = null;

        public static function get($key)
        {
            if (self::$getThrows) {
                throw new \RuntimeException('simulated cache read failure');
            }
            return self::$cachedValue;
        }

        public static function set($key, $value, $ttl = null)
        {
            if (self::$setThrows) {
                throw new \RuntimeException('simulated cache write failure');
            }
            self::$cachedValue = $value;
            return true;
        }

        public static function rm($key)
        {
            if (self::$rmThrows) {
                throw new \RuntimeException('simulated cache remove failure');
            }
            self::$cachedValue = null;
            return true;
        }
    }

    class Db
    {
        public static function table($name)
        {
            return new FakeQuery();
        }
    }

    class FakeQuery
    {
        public function field($fields) { return $this; }
        public function where($field, $value) { return $this; }
        public function order($order) { return $this; }
        public function select()
        {
            return [
                ['id' => 1, 'name' => 'A'],
                ['id' => 2, 'name' => 'B'],
            ];
        }
    }
}

namespace app\common\library {
    class SourceAppRecord
    {
        public static function publicSourceColumns()
        {
            return ['id', 'name'];
        }
    }

    require_once __DIR__ . '/../application/common/library/SourceAppRepository.php';

    function failopenAssert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL source_app_repository_failopen_test: {$message}\n");
            exit(1);
        }
    }

    \think\Cache::$getThrows = true;
    \think\Cache::$setThrows = false;
    $rows = SourceAppRepository::rows(true);
    failopenAssert(count($rows) === 2, 'database rows must survive cache read failure');
    failopenAssert(SourceAppRepository::lastSource() === 'db-cache-read-failed', 'read failure source marker missing');

    \think\Cache::$getThrows = false;
    \think\Cache::$cachedValue = null;
    \think\Cache::$setThrows = true;
    $rows = SourceAppRepository::rows(true);
    failopenAssert(count($rows) === 2, 'database rows must survive cache write failure');
    failopenAssert(SourceAppRepository::lastSource() === 'db-cache-write-failed', 'write failure source marker missing');

    \think\Cache::$setThrows = false;
    \think\Cache::$cachedValue = [['id' => 9, 'name' => 'cached']];
    $rows = SourceAppRepository::rows(true);
    failopenAssert(count($rows) === 1 && $rows[0]['id'] === 9, 'healthy cache hit changed');
    failopenAssert(SourceAppRepository::lastSource() === 'cache', 'cache hit source marker missing');

    \think\Cache::$rmThrows = true;
    SourceAppRepository::forget();
    failopenAssert(SourceAppRepository::lastSource() === 'none', 'cache remove failure must not escape');

    echo "source_app_repository_failopen_test: PASS\n";
}
