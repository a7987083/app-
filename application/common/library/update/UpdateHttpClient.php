<?php

namespace app\common\library\update;

class UpdateHttpClient
{
    const CONNECT_TIMEOUT = 5;
    const TIMEOUT = 30;
    const MAX_TEXT_BYTES = 2097152;

    public function get($url, $maxBytes = self::MAX_TEXT_BYTES)
    {
        $ch = $this->createHandle($url);
        if (!$ch) {
            return false;
        }
        $body = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) use (&$body, $maxBytes) {
            if (strlen($body) + strlen($data) > $maxBytes) {
                return 0;
            }
            $body .= $data;
            return strlen($data);
        });
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($ok === false || $errno || $status < 200 || $status >= 300) {
            error_log('[UpdateHttpClient] GET failed url=' . $url . ' status=' . $status . ' errno=' . $errno . ' error=' . $error);
            return false;
        }
        return $body;
    }

    public function download($url, $destination, $maxBytes = 104857600)
    {
        $dir = dirname($destination);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        $fp = @fopen($destination, 'wb');
        if (!$fp) {
            return false;
        }
        $written = 0;
        $ch = $this->createHandle($url);
        if (!$ch) {
            fclose($fp);
            return false;
        }
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) use ($fp, &$written, $maxBytes) {
            $length = strlen($data);
            $written += $length;
            if ($written > $maxBytes) {
                return 0;
            }
            $result = fwrite($fp, $data);
            return $result === false ? 0 : $result;
        });
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if ($ok === false || $errno || $status < 200 || $status >= 300 || !is_file($destination) || filesize($destination) <= 0) {
            @unlink($destination);
            error_log('[UpdateHttpClient] download failed url=' . $url . ' status=' . $status . ' errno=' . $errno . ' error=' . $error);
            return false;
        }
        return true;
    }

    protected function createHandle($url)
    {
        if (!$this->isHttpsUrl($url)) {
            error_log('[UpdateHttpClient] rejected non-HTTPS URL: ' . $url);
            return false;
        }
        $ch = curl_init($url);
        if (!$ch) {
            return false;
        }
        $verify = getenv('SOURCE_UPDATE_VERIFY_TLS');
        $verify = $verify === false || $verify === '' || $verify !== '0';
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'zonoe-source-updater/12');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json, text/plain, */*']);
        return $ch;
    }

    protected function isHttpsUrl($url)
    {
        $parts = @parse_url($url);
        return is_array($parts) && isset($parts['scheme'], $parts['host']) && strtolower($parts['scheme']) === 'https';
    }
}
