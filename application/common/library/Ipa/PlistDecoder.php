<?php

namespace app\common\library\Ipa;

class PlistDecoder
{
    protected $data;
    protected $offsets = [];
    protected $objectRefSize = 0;
    protected $numObjects = 0;
    protected $cache = [];

    public function decode($data)
    {
        $data = (string)$data;
        if (strncmp($data, 'bplist00', 8) === 0) {
            return $this->decodeBinary($data);
        }
        return $this->decodeXml($data);
    }

    protected function decodeXml($data)
    {
        $old = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        if ($xml === false) {
            throw new \RuntimeException('Invalid XML plist');
        }
        $children = $xml->children();
        if (!isset($children[0])) {
            return [];
        }
        return $this->xmlValue($children[0]);
    }

    protected function xmlValue($node)
    {
        $name = $node->getName();
        if ($name === 'dict') {
            $result = [];
            $key = null;
            foreach ($node->children() as $child) {
                if ($child->getName() === 'key') {
                    $key = (string)$child;
                    continue;
                }
                if ($key !== null) {
                    $result[$key] = $this->xmlValue($child);
                    $key = null;
                }
            }
            return $result;
        }
        if ($name === 'array') {
            $result = [];
            foreach ($node->children() as $child) {
                $result[] = $this->xmlValue($child);
            }
            return $result;
        }
        if ($name === 'true') return true;
        if ($name === 'false') return false;
        if ($name === 'integer') return (int)$node;
        if ($name === 'real') return (float)$node;
        if ($name === 'data') return base64_decode(preg_replace('/\s+/', '', (string)$node));
        return (string)$node;
    }

    protected function decodeBinary($data)
    {
        if (strlen($data) < 40) {
            throw new \RuntimeException('Truncated binary plist');
        }
        $this->data = $data;
        $this->cache = [];
        $trailer = substr($data, -32);
        $offsetIntSize = ord($trailer[6]);
        $this->objectRefSize = ord($trailer[7]);
        $this->numObjects = $this->readUInt(substr($trailer, 8, 8));
        $topObject = $this->readUInt(substr($trailer, 16, 8));
        $offsetTableOffset = $this->readUInt(substr($trailer, 24, 8));
        if ($offsetIntSize < 1 || $this->objectRefSize < 1 || $this->numObjects < 1 || $this->numObjects > 1000000) {
            throw new \RuntimeException('Invalid binary plist trailer');
        }
        $this->offsets = [];
        for ($i = 0; $i < $this->numObjects; $i++) {
            $pos = $offsetTableOffset + ($i * $offsetIntSize);
            $this->offsets[$i] = $this->readUInt(substr($data, $pos, $offsetIntSize));
        }
        return $this->parseObject($topObject, 0);
    }

    protected function parseObject($index, $depth)
    {
        $index = (int)$index;
        if ($depth > 128 || !isset($this->offsets[$index])) {
            throw new \RuntimeException('Invalid binary plist object graph');
        }
        if (array_key_exists($index, $this->cache)) {
            return $this->cache[$index];
        }
        $offset = $this->offsets[$index];
        if ($offset >= strlen($this->data)) {
            throw new \RuntimeException('Invalid binary plist object offset');
        }
        $marker = ord($this->data[$offset]);
        $type = $marker >> 4;
        $info = $marker & 0x0f;
        $cursor = $offset + 1;

        if ($type === 0x0) {
            if ($info === 0x8) return false;
            if ($info === 0x9) return true;
            return null;
        }
        if ($type === 0x1) {
            $size = 1 << $info;
            return $this->readUInt(substr($this->data, $cursor, $size));
        }
        if ($type === 0x2) {
            $size = 1 << $info;
            $bytes = substr($this->data, $cursor, $size);
            if ($size === 4) return $this->unpackFloat32($bytes);
            if ($size === 8) return $this->unpackFloat64($bytes);
            throw new \RuntimeException('Unsupported binary plist real');
        }
        if ($type === 0x3 && $info === 0x3) {
            $seconds = $this->unpackFloat64(substr($this->data, $cursor, 8));
            return gmdate('c', (int)round(978307200 + $seconds));
        }
        if ($type === 0x8) {
            return $this->readUInt(substr($this->data, $cursor, $info + 1));
        }

        list($length, $cursor) = $this->readLength($info, $cursor);
        if ($length > 67108864) {
            throw new \RuntimeException('Binary plist object too large');
        }
        if ($type === 0x4) {
            return substr($this->data, $cursor, $length);
        }
        if ($type === 0x5) {
            return substr($this->data, $cursor, $length);
        }
        if ($type === 0x6) {
            $raw = substr($this->data, $cursor, $length * 2);
            if (function_exists('mb_convert_encoding')) {
                return mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
            }
            if (function_exists('iconv')) {
                $converted = iconv('UTF-16BE', 'UTF-8//IGNORE', $raw);
                return $converted === false ? '' : $converted;
            }
            throw new \RuntimeException('UTF-16 decoder unavailable');
        }
        if ($type === 0xa) {
            $result = [];
            for ($i = 0; $i < $length; $i++) {
                $ref = $this->readUInt(substr($this->data, $cursor + ($i * $this->objectRefSize), $this->objectRefSize));
                $result[] = $this->parseObject($ref, $depth + 1);
            }
            $this->cache[$index] = $result;
            return $result;
        }
        if ($type === 0xd) {
            $result = [];
            $valuesCursor = $cursor + ($length * $this->objectRefSize);
            for ($i = 0; $i < $length; $i++) {
                $keyRef = $this->readUInt(substr($this->data, $cursor + ($i * $this->objectRefSize), $this->objectRefSize));
                $valueRef = $this->readUInt(substr($this->data, $valuesCursor + ($i * $this->objectRefSize), $this->objectRefSize));
                $key = (string)$this->parseObject($keyRef, $depth + 1);
                $result[$key] = $this->parseObject($valueRef, $depth + 1);
            }
            $this->cache[$index] = $result;
            return $result;
        }
        throw new \RuntimeException('Unsupported binary plist marker 0x' . dechex($marker));
    }

    protected function readLength($info, $cursor)
    {
        if ($info < 0x0f) {
            return [$info, $cursor];
        }
        $marker = ord($this->data[$cursor]);
        if (($marker >> 4) !== 0x1) {
            throw new \RuntimeException('Invalid binary plist extended length');
        }
        $size = 1 << ($marker & 0x0f);
        $length = $this->readUInt(substr($this->data, $cursor + 1, $size));
        return [$length, $cursor + 1 + $size];
    }

    protected function readUInt($bytes)
    {
        $value = 0;
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $value = ($value * 256) + ord($bytes[$i]);
        }
        return $value;
    }

    protected function unpackFloat32($bytes)
    {
        $v = unpack('Gvalue', $bytes);
        return (float)$v['value'];
    }

    protected function unpackFloat64($bytes)
    {
        if (PHP_VERSION_ID >= 70200) {
            $v = unpack('Evalue', $bytes);
            return (float)$v['value'];
        }
        $bytes = strrev($bytes);
        $v = unpack('dvalue', $bytes);
        return (float)$v['value'];
    }
}
