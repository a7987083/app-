<?php

namespace app\common\library;

use think\Cache;

/**
 * Shared cache for the existing Legacy /appstore protocol.
 *
 * The client wire format is untouched. Cache keys are scoped by the public
 * source revision/generation and by the effective card access set, so guest,
 * whole-source and App-specific entitlements can never share the wrong URLs.
 *
 * Cache failures are fail-open: legacy source delivery always falls back to
 * the existing mapper/encoder path.
 */
class SourceLegacyCache
{
    const APPS_TTL = 120;
    const BODY_TTL = 120;
    const ENCRYPTED_JSON_TTL = 120;

    protected static $context = null;
    protected static $appsState = 'none';
    protected static $bodyState = 'none';
    protected static $jsonState = 'none';

    public static function begin($mode, $sourceAccess)
    {
        $mode = $mode === 'licensed' ? 'licensed' : 'guest';
        $access = self::accessSignature($mode, $sourceAccess);
        $revision = 0;
        try {
            $revision = SourceChangeLog::currentRevision();
        } catch (\Throwable $e) {
            $revision = 0;
        }

        $generation = '0';
        try {
            $generation = SourceAppRepository::generation();
        } catch (\Throwable $e) {
            $generation = '0';
        }

        self::$context = [
            'mode' => $mode,
            'access' => $access,
            'revision' => (int)$revision,
            'generation' => (string)$generation,
        ];
        self::$appsState = 'miss';
        self::$bodyState = 'none';
        self::$jsonState = 'none';
        return self::$context;
    }

    public static function context()
    {
        return is_array(self::$context) ? self::$context : null;
    }

    public static function accessSignature($mode, $sourceAccess)
    {
        $mode = $mode === 'licensed' ? 'licensed' : 'guest';
        if ($mode === 'guest') {
            return 'guest';
        }

        if (!is_array($sourceAccess)) {
            return $sourceAccess ? 'all' : 'licensed:none';
        }
        if (!empty($sourceAccess['unlock_all'])) {
            return 'all';
        }

        $ids = isset($sourceAccess['app_ids']) && is_array($sourceAccess['app_ids'])
            ? self::normalizeIds($sourceAccess['app_ids'])
            : [];
        return $ids ? 'apps:' . sha1(implode(',', $ids)) : 'licensed:none';
    }

    public static function getMappedApps(array $context)
    {
        $key = self::appsKey($context);
        try {
            $cached = Cache::get($key);
            if (is_array($cached) && array_key_exists('value', $cached) && is_array($cached['value'])) {
                self::$appsState = 'hit';
                return $cached['value'];
            }
        } catch (\Throwable $e) {
            self::logFailure('apps-read', $e);
        }
        self::$appsState = 'miss';
        return null;
    }

    public static function storeMappedApps(array $context, array $apps)
    {
        try {
            Cache::set(self::appsKey($context), ['value' => $apps], self::APPS_TTL);
        } catch (\Throwable $e) {
            self::logFailure('apps-write', $e);
        }
        return $apps;
    }

    public static function getPlainBody(array $payload, $jsonFlags, $replaceMarkers)
    {
        $context = self::context();
        if (!$context || !isset($payload['apps']) || !is_array($payload['apps'])) {
            return null;
        }
        $key = self::bodyKey($context, $payload, $jsonFlags, $replaceMarkers);
        try {
            $cached = Cache::get($key);
            if (is_array($cached) && array_key_exists('body', $cached) && is_string($cached['body'])) {
                self::$bodyState = 'hit';
                return $cached['body'];
            }
        } catch (\Throwable $e) {
            self::logFailure('body-read', $e);
        }
        self::$bodyState = 'miss';
        return null;
    }

    public static function storePlainBody(array $payload, $jsonFlags, $replaceMarkers, $body)
    {
        $context = self::context();
        if (!$context || !isset($payload['apps']) || !is_array($payload['apps']) || !is_string($body)) {
            return $body;
        }
        $key = self::bodyKey($context, $payload, $jsonFlags, $replaceMarkers);
        try {
            Cache::set($key, ['body' => $body], self::BODY_TTL);
        } catch (\Throwable $e) {
            self::logFailure('body-write', $e);
        }
        return $body;
    }

    /**
     * Build the encrypted-path JSON without re-encoding the large App array on
     * every request. Only UDID/Time remain request-specific.
     *
     * The assembled bytes are intentionally equivalent to json_encode() over
     * AppStorePayload::source() with the same flags.
     */
    public static function encryptedJson(array $payload, $jsonFlags)
    {
        $context = self::context();
        if (!$context || !isset($payload['apps']) || !is_array($payload['apps'])) {
            return null;
        }

        $key = self::encryptedJsonKey($context, $payload, $jsonFlags);
        $static = null;
        try {
            $cached = Cache::get($key);
            if (is_array($cached)
                && isset($cached['prefix']) && is_string($cached['prefix'])
                && isset($cached['apps']) && is_string($cached['apps'])) {
                $static = $cached;
                self::$jsonState = 'hit';
            }
        } catch (\Throwable $e) {
            self::logFailure('encrypted-json-read', $e);
        }

        if ($static === null) {
            $static = self::buildEncryptedStatic($payload, $jsonFlags);
            if ($static === null) {
                self::$jsonState = 'fallback';
                return null;
            }
            self::$jsonState = 'miss';
            try {
                Cache::set($key, $static, self::ENCRYPTED_JSON_TTL);
            } catch (\Throwable $e) {
                self::logFailure('encrypted-json-write', $e);
            }
        }

        $udid = json_encode(array_key_exists('UDID', $payload) ? $payload['UDID'] : null, $jsonFlags);
        $time = json_encode(array_key_exists('Time', $payload) ? $payload['Time'] : null, $jsonFlags);
        if (!is_string($udid) || !is_string($time)) {
            self::$jsonState = 'fallback';
            return null;
        }

        $prefix = self::injectAnnouncementPrefix($static['prefix'], $payload, $jsonFlags);
        return $prefix
            . ',"UDID":' . $udid
            . ',"Time":' . $time
            . ',"apps":' . $static['apps']
            . '}';
    }

    /**
     * Pure byte-equivalence helper used by tests and cache-miss generation.
     */
    public static function buildEncryptedJson(array $payload, $jsonFlags)
    {
        $static = self::buildEncryptedStatic($payload, $jsonFlags);
        if ($static === null) {
            return null;
        }
        $udid = json_encode(array_key_exists('UDID', $payload) ? $payload['UDID'] : null, $jsonFlags);
        $time = json_encode(array_key_exists('Time', $payload) ? $payload['Time'] : null, $jsonFlags);
        if (!is_string($udid) || !is_string($time)) {
            return null;
        }
        $prefix = self::injectAnnouncementPrefix($static['prefix'], $payload, $jsonFlags);
        return $prefix
            . ',"UDID":' . $udid
            . ',"Time":' . $time
            . ',"apps":' . $static['apps']
            . '}';
    }

    public static function status()
    {
        return self::$appsState . '/' . self::$bodyState . '/' . self::$jsonState;
    }

    protected static function buildEncryptedStatic(array $payload, $jsonFlags)
    {
        $staticPayload = self::announcementPlaceholderPayload($payload);
        $site = [];
        foreach (AppStorePayload::SITE_KEYS as $key) {
            $site[$key] = array_key_exists($key, $staticPayload) ? $staticPayload[$key] : null;
        }

        $prefix = json_encode($site, $jsonFlags);
        $apps = json_encode($payload['apps'], $jsonFlags);
        if (!is_string($prefix) || !is_string($apps) || substr($prefix, -1) !== '}') {
            return null;
        }

        return [
            'prefix' => substr($prefix, 0, -1),
            'apps' => $apps,
        ];
    }

    protected static function announcementPlaceholderPayload(array $payload)
    {
        $announcementClass = __NAMESPACE__ . '\\SourceAnnouncementTemplate';
        if (class_exists($announcementClass)) {
            return SourceAnnouncementTemplate::placeholderPayload($payload);
        }
        return $payload;
    }

    protected static function injectAnnouncementPrefix($prefix, array $payload, $jsonFlags)
    {
        $announcementClass = __NAMESPACE__ . '\\SourceAnnouncementTemplate';
        if (!class_exists($announcementClass) || !array_key_exists('message', $payload)) {
            return $prefix;
        }
        return SourceAnnouncementTemplate::injectEncodedMessage($prefix, $payload['message'], $jsonFlags);
    }

    protected static function encryptedJsonKey(array $context, array $payload, $jsonFlags)
    {
        $site = [];
        foreach (AppStorePayload::SITE_KEYS as $key) {
            $site[$key] = array_key_exists($key, $payload) ? $payload[$key] : null;
        }
        $siteFingerprint = sha1(json_encode($site, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return 'zonoe_legacy_encrypted_json_v2_' . sha1(
            self::appsKey($context) . '|' . $siteFingerprint . '|' . (int)$jsonFlags
        );
    }

    protected static function appsKey(array $context)
    {
        return 'zonoe_legacy_apps_v1_' . sha1(
            (isset($context['revision']) ? (int)$context['revision'] : 0) . '|' .
            (isset($context['generation']) ? (string)$context['generation'] : '0') . '|' .
            (isset($context['mode']) ? (string)$context['mode'] : 'guest') . '|' .
            (isset($context['access']) ? (string)$context['access'] : 'guest')
        );
    }

    protected static function bodyKey(array $context, array $payload, $jsonFlags, $replaceMarkers)
    {
        $site = [];
        foreach (AppStorePayload::SITE_KEYS as $key) {
            $site[$key] = array_key_exists($key, $payload) ? $payload[$key] : null;
        }
        $siteFingerprint = sha1(json_encode($site, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return 'zonoe_legacy_body_v2_' . sha1(
            self::appsKey($context) . '|' . $siteFingerprint . '|' .
            (int)$jsonFlags . '|' . ($replaceMarkers ? '1' : '0')
        );
    }

    protected static function normalizeIds(array $ids)
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

    protected static function logFailure($operation, \Throwable $e)
    {
        error_log('[SourceLegacyCache] ' . $operation . ' failed; legacy response remains available: ' . $e->getMessage());
    }
}
