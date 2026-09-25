<?php

namespace app\common\library\Ipa;

use app\common\library\CardAccessPolicy;
use think\Db;

/**
 * 2406 runtime policy layer.
 *
 * Dylib validity and card/App permissions are deliberately separated:
 * - scope 2: basic in any App;
 * - scope 3: app_plus only when the server resolves the running App to a
 *   fa_category.id explicitly bound to that card;
 * - scope 1: global_plus in any App.
 *
 * Bundle ID is evidence, never the authority. scope=3 additionally requires a
 * parsed main Mach-O identity that is actively bound through
 * fa_ipa_category_binding.
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
            return [
                'resolved' => false,
                'code' => $protocol < 2 ? 'legacy_identity' : 'app_identity_incomplete',
                'category_id' => 0,
                'asset_id' => 0,
                'bundle_id' => $bundleId,
                'executable' => $executable,
                'macho_uuid' => $uuid,
            ];
        }

        $identities = Db::name('ipa_app_identity')
            ->where('bundle_id', $bundleId)
            ->where('executable', $executable)
            ->where('macho_uuid', $uuid)
            ->select();
        if (!$identities) {
            return [
                'resolved' => false,
                'code' => 'app_identity_unknown',
                'category_id' => 0,
                'asset_id' => 0,
                'bundle_id' => $bundleId,
                'executable' => $executable,
                'macho_uuid' => $uuid,
            ];
        }

        $assetIds = [];
        foreach ($identities as $identity) {
            $assetIds[(int)$identity['asset_id']] = true;
        }
        $assetIds = array_keys($assetIds);
        $bindings = Db::name('ipa_category_binding')
            ->where('asset_id', 'in', $assetIds)
            ->where('status', 'active')
            ->select();

        $categories = [];
        $assetByCategory = [];
        foreach ($bindings as $binding) {
            $categoryId = (int)$binding['category_id'];
            if ($categoryId <= 0) {
                continue;
            }
            $categories[$categoryId] = true;
            $assetByCategory[$categoryId] = (int)$binding['asset_id'];
        }
        $categoryIds = array_keys($categories);
        if (count($categoryIds) !== 1) {
            return [
                'resolved' => false,
                'code' => count($categoryIds) > 1 ? 'app_identity_ambiguous' : 'app_identity_unbound',
                'category_id' => 0,
                'asset_id' => 0,
                'bundle_id' => $bundleId,
                'executable' => $executable,
                'macho_uuid' => $uuid,
            ];
        }

        $categoryId = (int)$categoryIds[0];
        $category = Db::name('category')
            ->field('id,name,nickname,status,bt1a,bt2b')
            ->where('id', $categoryId)
            ->find();
        if (!$category || (string)$category['status'] !== 'normal' || (string)$category['bt2b'] !== '1') {
            return [
                'resolved' => false,
                'code' => 'app_identity_inactive',
                'category_id' => 0,
                'asset_id' => 0,
                'bundle_id' => $bundleId,
                'executable' => $executable,
                'macho_uuid' => $uuid,
            ];
        }

        return [
            'resolved' => true,
            'code' => 'ok',
            'category_id' => $categoryId,
            'asset_id' => isset($assetByCategory[$categoryId]) ? (int)$assetByCategory[$categoryId] : 0,
            'bundle_id' => $bundleId,
            'executable' => $executable,
            'macho_uuid' => $uuid,
            'name' => (string)$category['name'],
        ];
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
        $scope3Ids = [];
        foreach ($cards as $card) {
            $scope = CardAccessPolicy::scopeForRow($card);
            if ($scope === CardAccessPolicy::SCOPE_SOURCE) {
                return self::accessResult(self::ACCESS_GLOBAL_PLUS, 'global_source_card', $cards, $identity);
            }
            if ($scope === CardAccessPolicy::SCOPE_VERIFY) {
                $hasVerify = true;
                continue;
            }
            if ($scope === CardAccessPolicy::SCOPE_APPS) {
                $scope3Ids[] = (int)$card['id'];
            }
        }

        $categoryId = !empty($identity['resolved']) ? (int)$identity['category_id'] : 0;
        if ($categoryId > 0 && $scope3Ids) {
            $matched = Db::table('fa_kami_app')
                ->where('kami_id', 'in', $scope3Ids)
                ->where('app_id', $categoryId)
                ->find();
            if ($matched) {
                return self::accessResult(self::ACCESS_APP_PLUS, 'app_card_match', $cards, $identity);
            }
        }

        if ($hasVerify) {
            return self::accessResult(self::ACCESS_BASIC, 'verify_card', $cards, $identity);
        }

        if ($scope3Ids) {
            return self::accessResult(
                self::ACCESS_BLOCK,
                $categoryId > 0 ? 'app_not_authorized' : 'app_identity_required',
                $cards,
                $identity
            );
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
            $ids[(int)$binding['asset_id']] = true;
        }
        if (!$ids) {
            return ['available' => false];
        }
        $assets = Db::name('ipa_asset')
            ->field('id,source_id,path,app_name,app_version,build_version,modified_at,parsed_at')
            ->where('id', 'in', array_keys($ids))
            ->where('status', 'parsed')
            ->select();
        if (!$assets) {
            return ['available' => false];
        }
        usort($assets, function ($left, $right) {
            $version = version_compare((string)$right['app_version'], (string)$left['app_version']);
            if ($version !== 0) {
                return $version;
            }
            $build = version_compare((string)$right['build_version'], (string)$left['build_version']);
            if ($build !== 0) {
                return $build;
            }
            return ((int)$right['id'] <=> (int)$left['id']);
        });
        $latest = $assets[0];
        $available = self::isNewer(
            (string)$latest['app_version'],
            (string)$latest['build_version'],
            (string)$currentVersion,
            (string)$currentBuild
        );
        if (!$available) {
            return [
                'available' => false,
                'current_version' => (string)$currentVersion,
                'current_build' => (string)$currentBuild,
                'latest_version' => (string)$latest['app_version'],
                'latest_build' => (string)$latest['build_version'],
            ];
        }

        $config = self::runtimeConfig();
        $source = Db::name('ipa_source')->where('id', (int)$latest['source_id'])->find();
        $downloadUrl = $source
            ? IpaWritebackService::stableDownloadUrl((string)$source['base_url'], (string)$latest['path'])
            : '';
        $replace = [
            '{current_version}' => (string)$currentVersion,
            '{current_build}' => (string)$currentBuild,
            '{latest_version}' => (string)$latest['app_version'],
            '{latest_build}' => (string)$latest['build_version'],
            '{app_name}' => (string)$latest['app_name'],
        ];
        return [
            'available' => true,
            'current_version' => (string)$currentVersion,
            'current_build' => (string)$currentBuild,
            'latest_version' => (string)$latest['app_version'],
            'latest_build' => (string)$latest['build_version'],
            'latest_asset_id' => (int)$latest['id'],
            'title' => strtr((string)$config['update_title'], $replace),
            'message' => strtr((string)$config['update_message'], $replace),
            'primary_title' => (string)$config['update_primary_title'],
            'secondary_title' => (string)$config['update_secondary_title'],
            'download_url' => $downloadUrl,
        ];
    }

    public static function notice(array $identity, $accessLevel, $now)
    {
        $categoryId = !empty($identity['resolved']) ? (int)$identity['category_id'] : 0;
        $query = Db::name('dylib_runtime_notice')
            ->where('enabled', 1)
            ->where(function ($q) use ($categoryId) {
                $q->where('category_id', 0);
                if ($categoryId > 0) {
                    $q->whereOr('category_id', $categoryId);
                }
            })
            ->where(function ($q) use ($now) {
                $q->where('starts_at', 0)->whereOr('starts_at', '<=', (int)$now);
            })
            ->where(function ($q) use ($now) {
                $q->where('ends_at', 0)->whereOr('ends_at', '>', (int)$now);
            })
            ->order('category_id desc,priority desc,id desc');
        $rows = $query->select();
        foreach ($rows as $row) {
            $minimum = trim((string)$row['min_access_level']);
            if ($minimum !== '' && self::rank($accessLevel) < self::rank($minimum)) {
                continue;
            }
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
        if (!$row) {
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
        return $row;
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
            if ($cmp !== 0) {
                return $cmp > 0;
            }
        }
        if ($latestBuild !== '' && $currentBuild !== '') {
            return version_compare($latestBuild, $currentBuild) > 0;
        }
        return false;
    }

    protected static function rank($level)
    {
        switch ((string)$level) {
            case self::ACCESS_GLOBAL_PLUS:
                return 3;
            case self::ACCESS_APP_PLUS:
                return 2;
            case self::ACCESS_BASIC:
                return 1;
            default:
                return 0;
        }
    }

    protected static function button(array $row, $prefix)
    {
        $title = trim((string)$row[$prefix . '_title']);
        if ($title === '') {
            return null;
        }
        return [
            'title' => $title,
            'action' => (string)$row[$prefix . '_action'],
            'url' => (string)$row[$prefix . '_url'],
        ];
    }
}
