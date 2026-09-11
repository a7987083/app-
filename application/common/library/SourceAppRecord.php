<?php

namespace app\common\library;

/**
 * Semantic access layer for legacy fa_category column names.
 *
 * Physical columns stay untouched for deployment compatibility. Runtime code
 * refers to semantic keys instead of spreading bt1a/bt1b/bt2a/bt2b aliases.
 */
class SourceAppRecord
{
    const FIELD_MAP = [
        'id' => 'id',
        'parent_id' => 'pid',
        'type' => 'type',
        'name' => 'name',
        'version' => 'nickname',
        'description' => 'keywords',
        'download_url' => 'bt1a',
        'button_color' => 'bt1b',
        'file_size' => 'bt2a',
        'paid' => 'bt2b',
        'cloud_flag' => 'flag',
        'icon_url' => 'image',
        'updated_at' => 'updatetime',
        'remark' => 'beizhu',
        'weight' => 'weigh',
        'status' => 'status',
    ];

    public static function column($semantic)
    {
        if (!array_key_exists($semantic, self::FIELD_MAP)) {
            throw new \InvalidArgumentException('Unknown source-app field: ' . $semantic);
        }
        return self::FIELD_MAP[$semantic];
    }

    public static function value(array $row, $semantic, $default = null)
    {
        $column = self::column($semantic);
        return array_key_exists($column, $row) ? $row[$column] : $default;
    }

    public static function columns(array $semanticFields)
    {
        $columns = [];
        foreach ($semanticFields as $semantic) {
            $column = self::column($semantic);
            $columns[$column] = true;
        }
        return array_keys($columns);
    }

    public static function publicSourceColumns()
    {
        return self::columns([
            'type', 'name', 'version', 'description', 'download_url',
            'button_color', 'file_size', 'paid', 'cloud_flag', 'icon_url',
            'updated_at',
        ]);
    }
}
