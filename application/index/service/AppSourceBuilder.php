<?php

namespace app\index\service;

/**
 * Pure app-store payload builder.
 *
 * This class intentionally contains no database or HTTP dependencies so the
 * legacy /appstore transformation rules can be regression-tested in isolation.
 */
class AppSourceBuilder
{
    const LICENSE_NONE = 'none';
    const LICENSE_ACTIVE = 'active';
    const LICENSE_EXPIRED = 'expired';

    /**
     * Build the public apps array while preserving the legacy lock semantics.
     *
     * @param array  $list
     * @param string $licenseState
     * @return array
     */
    public static function buildApps(array $list, $licenseState)
    {
        $data = array();

        foreach ($list as $key => $val) {
            $type = $val['type'];
            if ($type == 'default') {
                $type = 0;
            }

            $data[$key] = array(
                'name' => $val['name'],
                'type' => $type,
                'version' => $val['nickname'],
                'versionDate' => date('Y-m-d\TH:i:s\+08:00', $val['updatetime']),
                'versionDescription' => str_replace('\\n', '@@@', $val['keywords']),
                'lock' => $val['bt2b'],
                'downloadURL' => self::resolveDownloadUrl($val['bt1a'], $val['bt2b'], $licenseState),
                'isLanZouCloud' => $val['flag'],
                'iconURL' => $val['image'],
                'tintColor' => $val['bt1b'],
                'size' => $val['bt2a'],
            );
        }

        return $data;
    }

    /**
     * Convert fa_config rows into the legacy source metadata map.
     *
     * @param array $config
     * @return array
     */
    public static function buildSiteInfo(array $config)
    {
        $info = array();

        foreach ($config as $val) {
            if ($val['name'] == 'name') {
                $info['name'] = $val['value'];
            }
            if ($val['name'] == 'message') {
                $info['message'] = $val['value'];
            }
            if ($val['name'] == 'identifier') {
                $info['identifier'] = $val['value'];
            }
            if ($val['name'] == 'sourceURL') {
                $info['sourceURL'] = $val['value'];
            }
            if ($val['name'] == 'sourceicon') {
                $info['sourceicon'] = $val['value'];
            }
            if ($val['name'] == 'payURL') {
                $info['payURL'] = $val['value'];
            }
            if ($val['name'] == 'unlockURL') {
                $info['unlockURL'] = $val['value'];
            }
        }

        return $info;
    }

    /**
     * Build the legacy source payload with its original key order.
     *
     * @param array  $info
     * @param array  $apps
     * @param string $udid
     * @param string $time
     * @return array
     */
    public static function buildPayload(array $info, array $apps, $udid, $time)
    {
        return array(
            'name' => $info['name'],
            'message' => $info['message'],
            'identifier' => $info['identifier'],
            'sourceURL' => $info['sourceURL'],
            'sourceicon' => $info['sourceicon'],
            'payURL' => $info['payURL'],
            'unlockURL' => $info['unlockURL'],
            'UDID' => $udid,
            'Time' => $time,
            'apps' => $apps,
        );
    }

    /**
     * Keep the three historical authorization branches byte-for-byte semantic:
     * - no license: any truthy lock hides the URL;
     * - active license: all URLs are visible;
     * - expired license: only lock == '1' hides the URL.
     */
    private static function resolveDownloadUrl($url, $lock, $licenseState)
    {
        if ($licenseState === self::LICENSE_ACTIVE) {
            return $url;
        }

        if ($licenseState === self::LICENSE_EXPIRED) {
            return $lock != '1' ? $url : '';
        }

        return $lock ? '' : $url;
    }
}
