<?php

namespace app\common\library\Ipa;

use app\common\library\CardAccessPolicy;
use think\Db;

/**
 * Runtime authorization policy for 2406.
 * Dylib validity is checked by DylibVerificationService; this class only
 * resolves the running App and derives the highest card permission that applies.
 */
class DylibRuntimeAccessService
{
    const ACCESS_BLOCK = 'block';
    const ACCESS_BASIC = 'basic';
    const ACCESS_APP_PLUS = 'app_plus';
    const ACCESS_GLOBAL_PLUS = 'global_plus';

    public static function resolveAppIdentity(array $payload)
    {
        $protocol = isset($payload['protocol_version']) ? (int)$payload['protocol_version'] : 1;
        $bundleId = trim((string)(isset($payload['bundle_id']) ? $payload['bundle_id'] : ''));
        $executable = trim((string)(isset($payload['app_executable']) ? $payload['app_executable'] : ''));
        $uuid = strtoupper(trim((string)(isset($payload['app_macho_uuid']) ? $payload['app_macho_uuid'] : '')));

        if ($protocol < 2 || $bundleId === '' || $executable === '' || $uuid === '') {
            return self::identity(false, $protocol < 2 ? 'legacy_identity' : 'app_identity_incomplete', 0, 0, $bundleId, $executable, $uuid);
        }

        $rows = Db::name('ipa_app_identity')
            ->field('asset_id')
            ->where('bundle_id', $bundleId)
            ->where('executable', $executable)
            ->where('macho_uuid', $uuid)
            ->select();
        $assetIds = [];
        foreach ($rows as $row) {
            $id = (int)$row['asset_id'];
            if ($id > 0) $assetIds[$id] = true;
        }
        if (!$assetIds) {
            return self::identity(false, 'app_identity_unknown', 0, 0, $bundleId, $executable, $uuid);
        }

        // Identity rows are enrichment data. They are never authoritative after
        // the parent IPA has left parsed state (for example after 清空解析结果).
        $parsedRows = Db::name('ipa_asset')
            ->field('id')
            ->where('id', 'in', array_keys($assetIds))
            ->where('status', 'parsed')
            ->select();
        $parsedIds = [];
        foreach ($parsedRows as $row) {
            $parsedIds[(int)$row['id']] = true;
        }
        if (!$parsedIds) {
            return self::identity(false, 'app_identity_stale', 0, 0, $bundleId, $executable, $uuid);
        }

        $bindings = Db::name('ipa_category_binding')
            ->field('asset_id,category_id')
            ->where('asset_id', 'in', array_keys($parsedIds))
            ->where('status', 'active')
            ->select();
        $categoryIds = [];
        $assetByCategory = [];
        foreach ($bindings as $binding) {
            $categoryId = (int)$binding['category_id'];
            if ($categoryId <= 0) continue;
            $categoryIds[$categoryId] = true;
            $assetByCategory[$categoryId] = max(isset($assetByCategory[$categoryId]) ? $assetByCategory[$categoryId] : 0, (int)$binding['asset_id']);
        }
        $categoryIds = array_keys($categoryIds);
        if (count($categoryIds) !== 1) {
            return self::identity(false, count($categoryIds) > 1 ? 'app_identity_ambiguous' : 'app_identity_unbound', 0, 0, $bundleId, $executable, $uuid);
        }

        $categoryId = (int)$categoryIds[0];
        $category = Db::name('category')
            ->field('id,name,status,bt2b')
            ->where('id', $categoryId)
            ->find();
        if (!$category || (string)$category['status'] !== 'normal' || (string)$category['bt2b'] !== '1') {
            return self::identity(false, 'app_identity_inactive', 0, 0, $bundleId, $executable, $uuid);
        }

        $result = self::identity(true, 'ok', $categoryId, (int)$assetByCategory[$categoryId], $bundleId, $executable, $uuid);
        $result['name'] = (string)$category['name'];
        return $result;
    }

    public static function resolveAccess($udid, $now, array $identity)
    {
        $cards = Db::name('kami')
            ->where('udid', (string)$udid)
            ->where('jh', 1)
            ->where('endtime', '>', (int)$now)
            ->order('endtime desc,id desc')
            ->select();
        if (!$cards) {
            return self::accessResult(self::ACCESS_BLOCK, 'license_invalid', [], $identity);
        }

        $hasVerify = false;
        $appCardIds = [];
        foreach ($cards as $card) {
            $scope = CardAccessPolicy::scopeForRow($card);
            if ($scope === CardAccessPolicy::SCOPE_SOURCE) {
                return self::accessResult(self::ACCESS_GLOBAL_PLUS, 'global_source_card', $cards, $identity);
            }
            if ($scope === CardAccessPolicy::SCOPE_VERIFY) {
                $hasVerify = true;
            } elseif ($scope === CardAccessPolicy::SCOPE_APPS) {
                $appCardIds[] = (int)$card['id'];
            }
        }

        $categoryId = !empty($identity['resolved']) ? (int)$identity['category_id'] : 0;
        if ($categoryId > 0 && $appCardIds) {
            $matched = Db::table('fa_kami_app')
                ->where('kami_id', 'in', $appCardIds)
                ->where('app_id', $categoryId)
                ->find();
            if ($matched) {
                return self::accessResult(self::ACCESS_APP_PLUS, 'app_card_match', $cards, $identity);
            }
        }

        if ($hasVerify) {
            return self::accessResult(self::ACCESS_BASIC, 'verify_card', $cards, $identity);
        }
        if ($appCardIds) {
            return self::accessResult(self::ACCESS_BLOCK, $categoryId > 0 ? 'app_not_authorized' : 'app_identity_required', $cards, $identity);
        }
        return self::accessResult(self::ACCESS_BLOCK, 'license_invalid', $cards, $identity);
    }

    public static function permissions($accessLevel)
    {
        $extra = in_array((string)$accessLevel, [self::ACCESS_APP_PLUS, self::ACCESS_GLOBAL_PLUS], true);
        return [
            'normal_menu' => (string)$accessLevel !== self::ACCESS_BLOCK,
            'extra_menu' => $extra,
            'extra_features' => $extra,
        ];
    }

    public static function appUpdate(array $identity, $currentVersion, $currentBuild)
    {
        if (empty($identity['resolved']) || (int)$identity['category_id'] <= 0) {
            return ['available' => false];
        }
        $categoryId = (int)$identity['category_id'];
        $bindings = Db::name('ipa_category_binding')
            ->field('asset_id')
            ->where('category_id', $categoryId)
            ->where('status', 'active')
            ->select();
        $ids = [];
        foreach ($bindings as $binding) {
            $id = (int)$binding['asset_id'];
            if ($id > 0) $ids[$id] = true;
        }
        if (!$ids) return ['available' => false];

        $assets = Db::name('ipa_asset')
            ->field('id,source_id,path,app_name,app_version,build_version,modified_at,parsed_at')
            ->where('id', 'in', array_keys($ids))
            ->where('status', 'parsed')
            ->select();
        if (!$assets) return ['available' => false];

        usort($assets, function ($left, $right) {
            $cmp = version_compare((string)$right['app_version'], (string)$left['app_version']);
            if ($cmp !== 0) return $cmp;
            $cmp = version_compare((string)$right['build_version'], (string)$left['build_version']);
            if ($cmp !== 0) return $cmp;
            return (int)$right['id'] <=> (int)$left['id'];
        });
        $latest = $assets[0];
        $available = self::isNewer((string)$latest['app_version'], (string)$latest['build_version'], (string)$currentVersion, (string)$currentBuild);
        $base = [
            'available' => $available,
            'current_version' => (string)$currentVersion,
            'current_build' => (string)$currentBuild,
            'latest_version' => (string)$latest['app_version'],
            'latest_build' => (string)$latest['build_version'],
        ];
        if (!$available) return $base;

        $config = self::runtimeConfig();
        $source = Db::name('ipa_source')->where('id', (int)$latest['source_id'])->find();
        $downloadUrl = $source ? IpaWritebackService::stableDownloadUrl((string)$source['base_url'], (string)$latest['path']) : '';
        $replace = [
            '{current_version}' => (string)$currentVersion,
            '{current_build}' => (string)$currentBuild,
            '{latest_version}' => (string)$latest['app_version'],
            '{latest_build}' => (string)$latest['build_version'],
            '{app_name}' => (string)$latest['app_name'],
        ];
        return array_merge($base, [
            'latest_asset_id' => (int)$latest['id'],
            'title' => strtr((string)$config['update_title'], $replace),
            'message' => strtr((string)$config['update_message'], $replace),
            'primary_title' => (string)$config['update_primary_title'],
            'secondary_title' => (string)$config['update_secondary_title'],
            'download_url' => $downloadUrl,
        ]);
    }

    public static function notice(array $identity, $accessLevel, $now)
    {
        $categoryId = !empty($identity['resolved']) ? (int)$identity['category_id'] : 0;
        $query = Db::name('dylib_runtime_notice')
            ->where('enabled', 1)
            ->where(function ($q) use ($categoryId) {
                $q->where('category_id', 0);
                if ($categoryId > 0) $q->whereOr('category_id', $categoryId);
            })
            ->where(function ($q) use ($now) {
                $q->where('starts_at', 0)->whereOr('starts_at', '<=', (int)$now);
            })
            ->where(function ($q) use ($now) {
                $q->where('ends_at', 0)->whereOr('ends_at', '>', (int)$now);
            })
            ->order('category_id desc,priority desc,id desc');
        foreach ($query->select() as $row) {
            $minimum = trim((string)$row['min_access_level']);
            if ($minimum !== '' && self::rank($accessLevel) < self::rank($minimum)) continue;
            return [
                'notice_key' => (string)$row['notice_key'],
                'revision' => (int)$row['revision'],
                'title' => (string)$row['title'],
                'message' => (string)$row['message'],
                'buttons' => array_values(array_filter([
                    self::button($row, 'primary'),
                    self::button($row, 'secondary'),
                ])),
            ];
        }
        return null;
    }

    public static function runtimeConfig()
    {
        $row = Db::name('dylib_runtime_config')->where('id', 1)->find();
        if ($row) return $row;
        return [
            'config_version' => 1,
            'api_endpoints_json' => '[]',
            'bootstrap_urls_json' => '[]',
            'verify_path' => '/index/dylib_verify/verify',
            'update_title' => '发现游戏新版本',
            'update_message' => '当前版本：{current_version} ({current_build})\n最新版本：{latest_version} ({latest_build})',
            'update_primary_title' => '前往更新',
            'update_secondary_title' => '稍后提醒',
        ];
    }

    protected static function identity($resolved, $code, $categoryId, $assetId, $bundleId, $executable, $uuid)
    {
        return [
            'resolved' => (bool)$resolved,
            'code' => (string)$code,
            'category_id' => (int)$categoryId,
            'asset_id' => (int)$assetId,
            'bundle_id' => (string)$bundleId,
            'executable' => (string)$executable,
            'macho_uuid' => (string)$uuid,
        ];
    }

    protected static function accessResult($level, $reason, array $cards, array $identity)
    {
        return [
            'access_level' => (string)$level,
            'reason' => (string)$reason,
            'permissions' => self::permissions($level),
            'card_count' => count($cards),
            'app_identity' => $identity,
        ];
    }

    protected static function isNewer($latestVersion, $latestBuild, $currentVersion, $currentBuild)
    {
        if ($latestVersion !== '' && $currentVersion !== '') {
            $cmp = version_compare($latestVersion, $currentVersion);
            if ($cmp !== 0) return $cmp > 0;
        }
        return $latestBuild !== '' && $currentBuild !== '' && version_compare($latestBuild, $currentBuild) > 0;
    }

    protected static function rank($level)
    {
        switch ((string)$level) {
            case self::ACCESS_GLOBAL_PLUS: return 3;
            case self::ACCESS_APP_PLUS: return 2;
            case self::ACCESS_BASIC: return 1;
            default: return 0;
        }
    }

    protected static function button(array $row, $prefix)
    {
        $title = trim((string)$row[$prefix . '_title']);
        if ($title === '') return null;
        return [
            'title' => $title,
            'action' => (string)$row[$prefix . '_action'],
            'url' => (string)$row[$prefix . '_url'],
        ];
    }
}
