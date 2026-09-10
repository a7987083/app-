<?php

namespace app\common\library;

use think\Db;

/**
 * 将数据库 fa_config 中本地 site.php 缺失的字段补进去，不覆盖已有配置。
 */
class SiteConfigSync
{
    /**
     * 更新包投放的一次性标记文件（copy_merge 的 glob 匹配不到点文件，故不用隐藏文件名）
     */
    public static function flagFile()
    {
        return ROOT_PATH . 'public' . DS . 'update' . DS . 'sync_site_config.flag';
    }

    /**
     * 存在标记则补全 site.php，成功后删除标记；失败则保留标记以便下次请求重试。
     */
    public static function runIfNeeded()
    {
        $flag = self::flagFile();
        if (!is_file($flag)) {
            return;
        }
        try {
            self::mergeMissingFromDb();
            @unlink($flag);
        } catch (\Exception $e) {
            // 保留标记，下次请求再试
        }
    }

    /**
     * 只追加缺失字段；site.php 不存在时按数据库整份生成。
     */
    public static function mergeMissingFromDb()
    {
        $siteFile = APP_PATH . 'extra' . DS . 'site.php';
        $exists = is_file($siteFile);
        $site = [];
        if ($exists) {
            $loaded = include $siteFile;
            if (is_array($loaded)) {
                $site = $loaded;
            }
        }

        $rows = Db::name('config')->select();
        if (empty($rows)) {
            return;
        }

        $changed = false;
        if (!$exists) {
            foreach ($rows as $row) {
                $site[$row['name']] = self::castValue($row);
            }
            $changed = true;
        } else {
            foreach ($rows as $row) {
                if (!array_key_exists($row['name'], $site)) {
                    $site[$row['name']] = self::castValue($row);
                    $changed = true;
                }
            }
        }

        if (!$changed) {
            return;
        }

        $dir = dirname($siteFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $siteFile,
            '<?php' . "\n\nreturn " . var_export($site, true) . ";"
        );
    }

    /**
     * 与后台 refreshFile() 保持同一套类型转换
     */
    protected static function castValue($row)
    {
        $value = isset($row['value']) ? $row['value'] : '';
        $type = isset($row['type']) ? $row['type'] : '';
        if (in_array($type, ['selects', 'checkbox', 'images', 'files'])) {
            return explode(',', $value);
        }
        if ($type == 'array') {
            return (array)json_decode($value, true);
        }
        return $value;
    }
}
