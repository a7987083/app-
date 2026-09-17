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
        'renewal_entry' => 'renewal_entry',
        'cloud_flag' => 'flag',
        'icon_url' => 'image',
        'updated_at' => 'updatetime',
        'remark' => 'beizhu',
        'weight' => 'weigh',
        'status' => 'status',
    ];

    protected static $renewalEntryAvailable = null;

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

    public static function publicSourceColumns($includeRenewalEntry = null)
    {
        if ($includeRenewalEntry === null) {
            $includeRenewalEntry = self::renewalEntryColumnAvailable();
        }

        $fields = [
            'id', 'type', 'name', 'version', 'description', 'download_url',
            'button_color', 'file_size', 'paid',
        ];
        if ($includeRenewalEntry) {
            $fields[] = 'renewal_entry';
        }
        $fields[] = 'cloud_flag';
        $fields[] = 'icon_url';
        $fields[] = 'updated_at';

        return self::columns($fields);
    }

    /**
     * Detect the optional renewal_entry column at runtime. The public source
     * must keep working even if the 2026091704 schema migration was missed.
     */
    public static function renewalEntryColumnAvailable()
    {
        if (self::$renewalEntryAvailable !== null) {
            return self::$renewalEntryAvailable;
        }

        if (!class_exists('think\\Db')) {
            self::$renewalEntryAvailable = false;
            return false;
        }

        try {
            $rows = \think\Db::query("SHOW COLUMNS FROM `fa_category` LIKE 'renewal_entry'");
            self::$renewalEntryAvailable = !empty($rows);
        } catch (\Exception $e) {
            error_log('[SourceAppRecord] renewal_entry schema detection failed: ' . $e->getMessage());
            self::$renewalEntryAvailable = false;
        }

        return self::$renewalEntryAvailable;
    }

    /**
     * Intersect known source columns with the columns physically present in
     * fa_category. Useful for schema-compatibility validation and migrations.
     */
    public static function publicSourceColumnsForSchema(array $availableColumns)
    {
        $available = [];
        foreach ($availableColumns as $column) {
            $column = (string)$column;
            if ($column !== '') {
                $available[$column] = true;
            }
        }

        $compatible = [];
        foreach (self::publicSourceColumns(true) as $column) {
            if (isset($available[$column])) {
                $compatible[] = $column;
            }
        }

        return $compatible;
    }
}
