<?php

namespace app\common\library;

/**
 * 更新包关键文件指纹，用于识别防篡改导致「版本号已变、文件未变」。
 */
class UpdateIntegrity
{
    /**
     * 参与校验的相对站点根目录路径（与更新包 program/ 内路径一致）
     */
    public static function files()
    {
        return [
            'application/admin/controller/general/Config.php',
            'public/assets/js/backend/index.js',
            'application/common/library/SiteConfigSync.php',
            'application/common/behavior/Common.php',
        ];
    }

    /**
     * 计算目录下关键文件的组合指纹
     * @param string $root 站点根或解压后的 program 目录
     * @return string
     */
    public static function signFromRoot($root)
    {
        $parts = [];
        foreach (self::files() as $rel) {
            $path = self::join($root, $rel);
            $parts[] = is_file($path) ? md5_file($path) : '';
        }
        return md5(implode(',', $parts));
    }

    /**
     * 将源目录（更新包 program）与目标站点根目录做 MD5 对比
     * 源中不存在的哨兵文件跳过，避免旧增量包误判
     * @return bool
     */
    public static function verifyAgainst($sourceRoot, $targetRoot)
    {
        $checked = 0;
        foreach (self::files() as $rel) {
            $src = self::join($sourceRoot, $rel);
            if (!is_file($src)) {
                continue;
            }
            $checked++;
            $dst = self::join($targetRoot, $rel);
            if (!is_file($dst) || md5_file($src) !== md5_file($dst)) {
                return false;
            }
        }
        return $checked > 0;
    }

    protected static function join($root, $rel)
    {
        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }
}
