<?php

namespace app\common\library\Ipa;

class MachOInspector
{
    const MH_MAGIC = 0xfeedface;
    const MH_CIGAM = 0xcefaedfe;
    const MH_MAGIC_64 = 0xfeedfacf;
    const MH_CIGAM_64 = 0xcffaedfe;
    const FAT_MAGIC = 0xcafebabe;
    const FAT_CIGAM = 0xbebafeca;
    const FAT_MAGIC_64 = 0xcafebabf;
    const FAT_CIGAM_64 = 0xbfbafeca;
    const LC_ID_DYLIB = 0x0d;
    const LC_UUID = 0x1b;

    public function inspect($bytes)
    {
        if (!is_string($bytes) || strlen($bytes) < 4) {
            throw new \InvalidArgumentException('Mach-O payload is too small');
        }

        $magic = $this->u32be($bytes, 0);
        if ($magic === self::FAT_MAGIC || $magic === self::FAT_MAGIC_64) {
            return $this->inspectFat($bytes, false, $magic === self::FAT_MAGIC_64);
        }
        if ($magic === self::FAT_CIGAM || $magic === self::FAT_CIGAM_64) {
            return $this->inspectFat($bytes, true, $magic === self::FAT_CIGAM_64);
        }

        return $this->inspectThin($bytes, 0);
    }

    protected function inspectFat($bytes, $littleEndian, $fat64)
    {
        if (strlen($bytes) < 8) {
            throw new \RuntimeException('Invalid FAT Mach-O header');
        }
        $count = $littleEndian ? $this->u32le($bytes, 4) : $this->u32be($bytes, 4);
        if ($count < 1 || $count > 64) {
            throw new \RuntimeException('Invalid FAT architecture count');
        }
        $entrySize = $fat64 ? 32 : 20;
        $need = 8 + ($count * $entrySize);
        if (strlen($bytes) < $need) {
            throw new \RuntimeException('Truncated FAT architecture table');
        }

        $architectures = [];
        $installName = '';
        $uuids = [];
        for ($i = 0; $i < $count; $i++) {
            $pos = 8 + ($i * $entrySize);
            $cpu = $littleEndian ? $this->u32le($bytes, $pos) : $this->u32be($bytes, $pos);
            $architectures[] = $this->cpuName($cpu);
            $offset = $fat64
                ? ($littleEndian ? $this->u64le($bytes, $pos + 8) : $this->u64be($bytes, $pos + 8))
                : ($littleEndian ? $this->u32le($bytes, $pos + 8) : $this->u32be($bytes, $pos + 8));
            if ($offset >= 0 && $offset + 4 <= strlen($bytes)) {
                try {
                    $thin = $this->inspectThin($bytes, (int)$offset);
                    if ($installName === '' && !empty($thin['install_name'])) {
                        $installName = $thin['install_name'];
                    }
                    if (!empty($thin['macho_uuid'])) {
                        $uuids[] = $thin['macho_uuid'];
                    }
                } catch (\Exception $e) {
                    // Architecture name from FAT table is still useful if slice bytes are not present.
                }
            }
        }

        $uuids = array_values(array_unique(array_filter($uuids)));
        return [
            'architectures' => array_values(array_unique(array_filter($architectures))),
            'install_name' => $installName,
            'macho_uuid' => count($uuids) === 1 ? $uuids[0] : '',
            'macho_uuids' => $uuids,
            'is_fat' => true,
        ];
    }

    protected function inspectThin($bytes, $offset)
    {
        if ($offset < 0 || $offset + 28 > strlen($bytes)) {
            throw new \RuntimeException('Truncated Mach-O header');
        }
        $raw = substr($bytes, $offset, 4);
        $be = unpack('N', $raw)[1];
        $le = unpack('V', $raw)[1];
        $littleEndian = false;
        $is64 = false;
        if ($le === self::MH_MAGIC || $le === self::MH_MAGIC_64) {
            $littleEndian = true;
            $is64 = $le === self::MH_MAGIC_64;
        } elseif ($be === self::MH_MAGIC || $be === self::MH_MAGIC_64) {
            $littleEndian = false;
            $is64 = $be === self::MH_MAGIC_64;
        } else {
            throw new \RuntimeException('Not a Mach-O binary');
        }

        $read32 = function ($pos) use ($bytes, $littleEndian) {
            return $littleEndian ? $this->u32le($bytes, $pos) : $this->u32be($bytes, $pos);
        };
        $cpu = $read32($offset + 4);
        $ncmds = $read32($offset + 16);
        $sizeofcmds = $read32($offset + 20);
        $headerSize = $is64 ? 32 : 28;
        if ($ncmds > 100000 || $sizeofcmds > 64 * 1024 * 1024) {
            throw new \RuntimeException('Invalid Mach-O load commands');
        }
        $cmdPos = $offset + $headerSize;
        $cmdEnd = $cmdPos + $sizeofcmds;
        if ($cmdEnd > strlen($bytes)) {
            $cmdEnd = strlen($bytes);
        }
        $installName = '';
        $machoUuid = '';
        for ($i = 0; $i < $ncmds && $cmdPos + 8 <= $cmdEnd; $i++) {
            $cmd = $read32($cmdPos);
            $cmdSize = $read32($cmdPos + 4);
            if ($cmdSize < 8 || $cmdPos + $cmdSize > $cmdEnd) {
                break;
            }
            if (($cmd & 0x7fffffff) === self::LC_ID_DYLIB && $cmdSize >= 24) {
                $nameOffset = $read32($cmdPos + 8);
                if ($nameOffset >= 24 && $nameOffset < $cmdSize) {
                    $start = $cmdPos + $nameOffset;
                    $length = $cmdSize - $nameOffset;
                    $name = substr($bytes, $start, $length);
                    $nul = strpos($name, "\0");
                    if ($nul !== false) {
                        $name = substr($name, 0, $nul);
                    }
                    $installName = trim($name);
                }
            }
            if (($cmd & 0x7fffffff) === self::LC_UUID && $cmdSize >= 24 && $cmdPos + 24 <= strlen($bytes)) {
                $machoUuid = $this->formatUuid(substr($bytes, $cmdPos + 8, 16));
            }
            $cmdPos += $cmdSize;
        }

        return [
            'architectures' => [$this->cpuName($cpu)],
            'install_name' => $installName,
            'macho_uuid' => $machoUuid,
            'macho_uuids' => $machoUuid !== '' ? [$machoUuid] : [],
            'is_fat' => false,
        ];
    }

    protected function formatUuid($bytes)
    {
        if (!is_string($bytes) || strlen($bytes) !== 16) {
            return '';
        }
        $hex = strtoupper(bin2hex($bytes));
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    protected function cpuName($cpu)
    {
        $cpu = (int)$cpu;
        $base = $cpu & 0x00ffffff;
        $abi64 = ($cpu & 0x01000000) !== 0;
        if ($base === 12) {
            return $abi64 ? 'arm64' : 'arm';
        }
        if ($base === 7) {
            return $abi64 ? 'x86_64' : 'i386';
        }
        return sprintf('cpu:0x%08x', $cpu & 0xffffffff);
    }

    protected function u32be($bytes, $offset)
    {
        if ($offset + 4 > strlen($bytes)) throw new \RuntimeException('Truncated binary');
        return (int)unpack('N', substr($bytes, $offset, 4))[1];
    }

    protected function u32le($bytes, $offset)
    {
        if ($offset + 4 > strlen($bytes)) throw new \RuntimeException('Truncated binary');
        return (int)unpack('V', substr($bytes, $offset, 4))[1];
    }

    protected function u64be($bytes, $offset)
    {
        $hi = $this->u32be($bytes, $offset);
        $lo = $this->u32be($bytes, $offset + 4);
        return ($hi * 4294967296) + $lo;
    }

    protected function u64le($bytes, $offset)
    {
        $lo = $this->u32le($bytes, $offset);
        $hi = $this->u32le($bytes, $offset + 4);
        return ($hi * 4294967296) + $lo;
    }
}
