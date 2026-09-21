<?php

namespace app\common\library\Ipa;

use app\common\library\SourceAppRepository;
use app\common\library\SourceChangeLog;
use think\Db;

class IpaWritebackService
{
    protected static $allowed = ['name', 'nickname', 'bt1a', 'bt2a'];

    public static function preview($assetId, $categoryId)
    {
        list($asset, $source, $category) = self::load($assetId, $categoryId);
        $proposed = [
            'name' => (string)$asset['app_name'],
            'nickname' => (string)$asset['app_version'],
            'bt1a' => self::stableDownloadUrl($source['base_url'], $asset['path']),
            'bt2a' => (int)$asset['size_bytes'],
        ];
        $current = [];
        $diff = [];
        foreach (self::$allowed as $field) {
            $current[$field] = isset($category[$field]) ? $category[$field] : null;
            $diff[$field] = (string)$current[$field] !== (string)$proposed[$field];
        }
        $warnings = [];
        if ($asset['status'] !== 'parsed') $warnings[] = 'IPA is not in parsed state';
        if ($proposed['name'] === '') $warnings[] = 'IPA app name is empty';
        if ($proposed['nickname'] === '') $warnings[] = 'IPA app version is empty';
        if (strlen($proposed['name']) > 30) $warnings[] = 'App name exceeds fa_category.name length 30';
        if (strlen($proposed['nickname']) > 50) $warnings[] = 'Version exceeds fa_category.nickname length 50';

        return [
            'asset' => ['id' => (int)$asset['id'], 'bundle_id' => $asset['bundle_id'], 'path' => $asset['path']],
            'category' => ['id' => (int)$category['id'], 'name' => $category['name']],
            'current' => $current,
            'proposed' => $proposed,
            'diff' => $diff,
            'warnings' => $warnings,
        ];
    }

    public static function apply($assetId, $categoryId, array $fields, $adminId)
    {
        list($asset, $source, $category) = self::load($assetId, $categoryId);
        if ($asset['status'] !== 'parsed') {
            throw new \RuntimeException('Only parsed IPA assets can be written back');
        }
        $preview = self::preview($assetId, $categoryId);
        $data = [];
        foreach ($fields as $field) {
            $field = (string)$field;
            if (!in_array($field, self::$allowed, true)) continue;
            $data[$field] = $preview['proposed'][$field];
        }
        if (!$data) {
            throw new \InvalidArgumentException('No writeback fields selected');
        }
        if (isset($data['name']) && ($data['name'] === '' || strlen($data['name']) > 30)) {
            throw new \InvalidArgumentException('Invalid app name for fa_category');
        }
        if (isset($data['nickname']) && ($data['nickname'] === '' || strlen($data['nickname']) > 50)) {
            throw new \InvalidArgumentException('Invalid version for fa_category');
        }

        $now = time();
        Db::startTrans();
        try {
            $changed = Db::name('category')->where('id', (int)$categoryId)->update($data);
            $binding = Db::name('ipa_category_binding')
                ->where('asset_id', (int)$assetId)
                ->where('category_id', (int)$categoryId)
                ->find();
            $bindingData = ['status' => 'active', 'updated_at' => $now];
            if ($binding) {
                Db::name('ipa_category_binding')->where('id', (int)$binding['id'])->update($bindingData);
            } else {
                $bindingData['asset_id'] = (int)$assetId;
                $bindingData['category_id'] = (int)$categoryId;
                $bindingData['created_by'] = (int)$adminId;
                $bindingData['created_at'] = $now;
                Db::name('ipa_category_binding')->insert($bindingData);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        if ((int)$changed > 0) {
            SourceAppRepository::forget();
            SourceChangeLog::record((int)$categoryId, 'update');
        }
        return ['changed' => (int)$changed, 'fields' => array_keys($data)];
    }

    public static function stableDownloadUrl($baseUrl, $path)
    {
        $segments = explode('/', ltrim((string)$path, '/'));
        foreach ($segments as &$segment) {
            $segment = rawurlencode($segment);
        }
        unset($segment);
        return rtrim((string)$baseUrl, '/') . '/d/' . implode('/', $segments);
    }

    protected static function load($assetId, $categoryId)
    {
        $asset = Db::name('ipa_asset')->where('id', (int)$assetId)->find();
        if (!$asset) throw new \InvalidArgumentException('IPA asset not found');
        $source = Db::name('ipa_source')->where('id', (int)$asset['source_id'])->find();
        if (!$source) throw new \InvalidArgumentException('IPA source not found');
        $category = Db::name('category')->where('id', (int)$categoryId)->find();
        if (!$category) throw new \InvalidArgumentException('Category not found');
        return [$asset, $source, $category];
    }
}
