<?php

namespace app\common\library;

/**
 * Admin-selected software-source encryption mode.
 *
 * fa_config.opencry is kept for backwards compatibility:
 *   0 = plaintext
 *   1 = legacy/normal appstore encryption
 *   2 = appstore_v2 encryption
 *
 * The selected server mode is authoritative. Request headers are only used as
 * a compatibility fallback while encryption is disabled or when the config
 * store is unavailable to an isolated test/runtime context.
 */
class SourceEncryptionMode
{
    const OFF = '0';
    const NORMAL = '1';
    const V2 = '2';

    public static function normalize($value)
    {
        $value = trim((string)$value);
        return in_array($value, [self::OFF, self::NORMAL, self::V2], true)
            ? $value
            : self::OFF;
    }

    /**
     * Return the raw admin-selected mode, or null when config access is not
     * available. null intentionally preserves the historical header fallback.
     */
    public static function selected($useCache = true)
    {
        if (!class_exists('think\\Db') || !class_exists('think\\Cache')) {
            return null;
        }

        try {
            return self::normalize(SourceConfigRepository::rawValue('opencry', self::OFF, $useCache));
        } catch (\Exception $e) {
            error_log('[SourceEncryptionMode] config read failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Pure resolver used by runtime and contracts. A non-null server mode is
     * authoritative; null preserves the historical request-header behavior.
     */
    public static function appTypeForMode($mode, $headerValue = null)
    {
        if ($mode !== null) {
            $mode = self::normalize($mode);
            if ($mode === self::V2) {
                return 'appstore_v2';
            }
            if ($mode === self::NORMAL) {
                return 'appstore';
            }
        }

        return $headerValue === 'v2' ? 'appstore_v2' : 'appstore';
    }

    /**
     * Resolve the wire protocol. When encryption is enabled, the server-side
     * selection wins so normal and V2 can never be active simultaneously.
     */
    public static function appType($headerValue = null, $useCache = true)
    {
        return self::appTypeForMode(self::selected($useCache), $headerValue);
    }

    /**
     * Legacy callers only understand opencry as an on/off flag.
     */
    public static function runtimeEnabledValue($value)
    {
        return self::normalize($value) === self::OFF ? '0' : '1';
    }
}
