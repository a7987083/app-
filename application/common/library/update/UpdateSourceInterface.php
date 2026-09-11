<?php

namespace app\common\library\update;

interface UpdateSourceInterface
{
    /**
     * Return the latest normalized package metadata or null when no publishable update exists.
     */
    public function latest();

    /**
     * Return normalized packages that should be applied in ascending version order.
     */
    public function packagesAfter($localVersion, $force = false);

    /**
     * Whether SHA256 is mandatory for packages from this source.
     */
    public function requiresSha256();

    public function name();
}
