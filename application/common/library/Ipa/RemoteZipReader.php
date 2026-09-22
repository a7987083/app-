<?php

namespace app\common\library\Ipa;

class RemoteZipReader
{
    protected $http;
    protected $entries;

    public function __construct(HttpRangeClient $http)
    {
        $this->http = $http;
    }

    public function entries()
    {
        if ($this->entries !== null) {
            return $this->entries;
        }
        $size = $this->http->size();
        $tailLength = min($size, 65557);
        $tail = $this->http->getRange($size - $tailLength, $tailLength);
        $pos = strrpos($tail, "PK\x05\x06");
        if ($pos === false || strlen($tail) < $pos + 22) {
            throw new \RuntimeException('ZIP EOCD not found');
        }
        $eocd = substr($tail, $pos, 22);
        $v = unpack('Vsig/vdisk/vcdDisk/ventriesDisk/ventries/VcdSize/VcdOffset/vcommentLen', $eocd);
        if ((int)$v['disk'] !== 0 || (int)$v['cdDisk'] !== 0) {
            throw new \RuntimeException('Multi-disk ZIP is not supported');
        }
        if ((int)$v['entries'] === 0xffff || (int)$v['cdSize'] === 0xffffffff || (int)$v['cdOffset'] === 0xffffffff) {
            throw new \RuntimeException('ZIP64 is not supported in v1 parser');
        }
        $cd = $this->http->getRange((int)$v['cdOffset'], (int)$v['cdSize']);
        $offset = 0;
        $entries = [];
        $count = strlen($cd);
        while ($offset + 46 <= $count) {
            if (substr($cd, $offset, 4) !== "PK\x01\x02") {
                break;
            }
            $h = unpack('Vsig/vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLen/vextraLen/vcommentLen/vdisk/vintAttr/VextAttr/VlocalOffset', substr($cd, $offset, 46));
            $nameLen = (int)$h['nameLen'];
            $extraLen = (int)$h['extraLen'];
            $commentLen = (int)$h['commentLen'];
            $recordLen = 46 + $nameLen + $extraLen + $commentLen;
            if ($offset + $recordLen > $count) {
                throw new \RuntimeException('Truncated ZIP central directory');
            }
            $name = substr($cd, $offset + 46, $nameLen);
            if (($h['flags'] & 0x800) !== 0 && function_exists('mb_convert_encoding')) {
                $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
            }
            $entries[$name] = [
                'name' => $name,
                'method' => (int)$h['method'],
                'flags' => (int)$h['flags'],
                'crc32' => sprintf('%08x', (int)$h['crc']),
                'compressed_size' => (int)$h['compressed'],
                'uncompressed_size' => (int)$h['uncompressed'],
                'local_offset' => (int)$h['localOffset'],
            ];
            $offset += $recordLen;
        }
        if (!$entries) {
            throw new \RuntimeException('ZIP central directory is empty or invalid');
        }
        $this->entries = $entries;
        return $entries;
    }

    public function findFirst($pattern)
    {
        foreach ($this->entries() as $name => $entry) {
            if (preg_match($pattern, $name)) {
                return $entry;
            }
        }
        return null;
    }

    protected function dataOffset(array $entry)
    {
        $local = $this->http->getRange((int)$entry['local_offset'], 30);
        if (substr($local, 0, 4) !== "PK\x03\x04") {
            throw new \RuntimeException('Invalid ZIP local header');
        }
        $h = unpack('Vsig/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLen/vextraLen', $local);
        return (int)$entry['local_offset'] + 30 + (int)$h['nameLen'] + (int)$h['extraLen'];
    }

    /**
     * Extract only the first part of a ZIP member. This is intentionally bounded and is used
     * for Mach-O headers/load commands so large executables are never inflated into PHP memory.
     */
    public function extractPrefix(array $entry, $maxOutputBytes = 2097152, $chunkBytes = 262144)
    {
        if (($entry['flags'] & 0x1) !== 0) {
            throw new \RuntimeException('Encrypted ZIP entry is not supported');
        }
        $maxOutputBytes = max(4096, min(8 * 1024 * 1024, (int)$maxOutputBytes));
        $chunkBytes = max(32768, min(1024 * 1024, (int)$chunkBytes));
        $dataOffset = $this->dataOffset($entry);
        $compressedSize = (int)$entry['compressed_size'];

        if ((int)$entry['method'] === 0) {
            $length = min($maxOutputBytes, (int)$entry['uncompressed_size']);
            return $length > 0 ? $this->http->getRange($dataOffset, $length) : '';
        }
        if ((int)$entry['method'] !== 8) {
            throw new \RuntimeException('Unsupported ZIP compression method ' . (int)$entry['method']);
        }
        if (!function_exists('inflate_init') || !function_exists('inflate_add')) {
            throw new \RuntimeException('Streaming inflate is unavailable');
        }

        $ctx = inflate_init(ZLIB_ENCODING_RAW);
        if ($ctx === false) {
            throw new \RuntimeException('Unable to initialize streaming inflate');
        }
        $out = '';
        $read = 0;
        while ($read < $compressedSize && strlen($out) < $maxOutputBytes) {
            $length = min($chunkBytes, $compressedSize - $read);
            $chunk = $this->http->getRange($dataOffset + $read, $length);
            if ($chunk === '') {
                break;
            }
            $read += strlen($chunk);
            $flush = $read >= $compressedSize ? ZLIB_FINISH : ZLIB_SYNC_FLUSH;
            $piece = @inflate_add($ctx, $chunk, $flush);
            if ($piece === false) {
                throw new \RuntimeException('Unable to inflate ZIP prefix');
            }
            if ($piece !== '') {
                $remaining = $maxOutputBytes - strlen($out);
                $out .= strlen($piece) > $remaining ? substr($piece, 0, $remaining) : $piece;
            }
        }
        return $out;
    }

    public function extract(array $entry, $maxUncompressedBytes = 67108864)
    {
        if (($entry['flags'] & 0x1) !== 0) {
            throw new \RuntimeException('Encrypted ZIP entry is not supported');
        }
        if ((int)$entry['uncompressed_size'] > (int)$maxUncompressedBytes) {
            throw new \RuntimeException('ZIP entry exceeds extraction limit');
        }
        $dataOffset = $this->dataOffset($entry);
        $compressed = $this->http->getRange($dataOffset, (int)$entry['compressed_size']);
        if ((int)$entry['method'] === 0) {
            $data = $compressed;
        } elseif ((int)$entry['method'] === 8) {
            $data = @gzinflate($compressed);
            if ($data === false) {
                throw new \RuntimeException('Unable to inflate ZIP entry');
            }
        } else {
            throw new \RuntimeException('Unsupported ZIP compression method ' . (int)$entry['method']);
        }
        if (strlen($data) !== (int)$entry['uncompressed_size']) {
            throw new \RuntimeException('ZIP entry size mismatch');
        }
        return $data;
    }
}
