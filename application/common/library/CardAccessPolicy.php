<?php

namespace app\common\library;

/**
 * Card permission scopes.
 *
 * Scope is independent from card duration (kmyp):
 * 1 = unlock all paid apps in the source
 * 2 = verification-only; never grants source download access
 * 3 = unlock only apps mapped through fa_kami_app
 *
 * Legacy rows without card_scope are treated as scope 1 so already-issued
 * cards preserve their historical behaviour after upgrading.
 */
class CardAccessPolicy
{
    const SCOPE_SOURCE = 1;
    const SCOPE_VERIFY = 2;
    const SCOPE_APPS = 3;

    public static function normalizeScope($scope)
    {
        $scope = (int)$scope;
        return in_array($scope, [self::SCOPE_SOURCE, self::SCOPE_VERIFY, self::SCOPE_APPS], true)
            ? $scope
            : self::SCOPE_SOURCE;
    }

    public static function scopeForRow(array $row)
    {
        return self::normalizeScope(isset($row['card_scope']) ? $row['card_scope'] : self::SCOPE_SOURCE);
    }

    public static function scopeName($scope)
    {
        switch (self::normalizeScope($scope)) {
            case self::SCOPE_VERIFY:
                return '仅验证';
            case self::SCOPE_APPS:
                return '指定App';
            case self::SCOPE_SOURCE:
            default:
                return '全软件源';
        }
    }

    public static function normalizeAppIds(array $ids)
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $clean[$id] = true;
            }
        }
        $ids = array_keys($clean);
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    public static function sameAppSet(array $left, array $right)
    {
        return self::normalizeAppIds($left) === self::normalizeAppIds($right);
    }

    public static function isActive(array $row, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        return (int)(isset($row['jh']) ? $row['jh'] : 0) === 1
            && (int)(isset($row['endtime']) ? $row['endtime'] : 0) > $now;
    }

    /**
     * Build source-download permissions from active cards.
     * Verification-only cards are intentionally ignored.
     *
     * $appsByCardId is [kami_id => [app_id, ...]].
     */
    public static function sourceAccess(array $rows, array $appsByCardId = [], $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $unlockAll = false;
        $allowed = [];
        foreach ($rows as $row) {
            if (!self::isActive($row, $now)) {
                continue;
            }
            $scope = self::scopeForRow($row);
            if ($scope === self::SCOPE_SOURCE) {
                $unlockAll = true;
                continue;
            }
            if ($scope !== self::SCOPE_APPS) {
                continue;
            }
            $kamiId = isset($row['id']) ? (int)$row['id'] : 0;
            if ($kamiId <= 0 || !isset($appsByCardId[$kamiId])) {
                continue;
            }
            foreach (self::normalizeAppIds((array)$appsByCardId[$kamiId]) as $appId) {
                $allowed[$appId] = true;
            }
        }
        $allowedIds = array_keys($allowed);
        sort($allowedIds, SORT_NUMERIC);
        return [
            'unlock_all' => $unlockAll,
            'app_ids' => $allowedIds,
        ];
    }

    public static function allowsApp(array $access, $appId)
    {
        if (!empty($access['unlock_all'])) {
            return true;
        }
        $appId = (int)$appId;
        if ($appId <= 0) {
            return false;
        }
        $allowed = isset($access['app_ids']) && is_array($access['app_ids'])
            ? self::normalizeAppIds($access['app_ids'])
            : [];
        return in_array($appId, $allowed, true);
    }

    public static function hasSourceCard(array $rows)
    {
        foreach ($rows as $row) {
            $scope = self::scopeForRow($row);
            if ($scope === self::SCOPE_SOURCE || $scope === self::SCOPE_APPS) {
                return true;
            }
        }
        return false;
    }
}
