<?php

namespace app\common\library\Ipa;

/**
 * 2026092426 compatibility shim.
 *
 * The legacy parser implementation was removed. This class exists only so an
 * online upgrade overwrites the old file on 2425 installations and any stale
 * caller is routed to the fast Parser V2 implementation.
 */
class IpaParserService
{
    public static function parseAsset($assetId, array $source, $token = '')
    {
        return IpaParserV2Service::parseAsset($assetId, $source, $token);
    }

    public static function markParseError($assetId, \Exception $e)
    {
        return IpaParserV2Service::markParseError($assetId, $e);
    }
}
