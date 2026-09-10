<?php

namespace app\common\library;

/**
 * Generates card codes while preserving the visible legacy format:
 * uppercase prefix + 12 hexadecimal characters.
 */
class CardCodeGenerator
{
    const SUFFIX_BYTES = 6;
    const SUFFIX_LENGTH = 12;
    const MAX_CODE_LENGTH = 128;

    public static function generate($prefix = '')
    {
        $prefix = trim((string)$prefix);
        if (strlen($prefix) > self::MAX_CODE_LENGTH - self::SUFFIX_LENGTH) {
            throw new \InvalidArgumentException('卡密前缀过长');
        }

        return strtoupper($prefix . bin2hex(random_bytes(self::SUFFIX_BYTES)));
    }

    public static function generateBatch($count, $prefix = '')
    {
        $count = (int)$count;
        if ($count <= 0) {
            throw new \InvalidArgumentException('数量需大于0');
        }

        $codes = [];
        while (count($codes) < $count) {
            $code = self::generate($prefix);
            $codes[$code] = true;
        }

        return array_keys($codes);
    }

    /**
     * Generate a batch that does not collide with existing database codes.
     *
     * $existingResolver receives the candidate array and returns the subset
     * that already exists. A small retry bound prevents an unexpected storage
     * problem from spinning forever.
     */
    public static function generateUniqueBatch($count, $prefix, callable $existingResolver, $maxAttempts = 5)
    {
        $count = (int)$count;
        $maxAttempts = max(1, (int)$maxAttempts);
        $codes = self::generateBatch($count, $prefix);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $existing = (array)call_user_func($existingResolver, $codes);
            if (!$existing) {
                return $codes;
            }

            $existingMap = [];
            foreach ($existing as $code) {
                $existingMap[strtoupper((string)$code)] = true;
            }

            $kept = [];
            $blocked = $existingMap;
            foreach ($codes as $code) {
                $normalized = strtoupper((string)$code);
                if (!isset($existingMap[$normalized])) {
                    $kept[] = $code;
                    $blocked[$normalized] = true;
                }
            }

            while (count($kept) < $count) {
                $candidate = self::generate($prefix);
                if (isset($blocked[$candidate])) {
                    continue;
                }
                $blocked[$candidate] = true;
                $kept[] = $candidate;
            }
            $codes = $kept;
        }

        throw new \RuntimeException('无法生成唯一卡密，请重试');
    }
}
