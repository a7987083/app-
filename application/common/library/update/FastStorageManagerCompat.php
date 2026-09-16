<?php

namespace app\common\library\update;

/**
 * open_basedir-safe wrapper for FastStorageManager.
 * Avoids filesystem probes against PHP_BINDIR, which may live outside the
 * website's allowed path on Baota/PHP-FPM installations.
 */
class FastStorageManagerCompat extends FastStorageManager
{
    protected $phpCliCommand = '';

    protected function engineCapabilities()
    {
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        $exec = function_exists('exec') && !in_array('exec', $disabled, true);
        $proc = function_exists('proc_open') && !in_array('proc_open', $disabled, true);

        $find = false;
        if ($exec) {
            $out = [];
            $code = 0;
            @exec('command -v find 2>/dev/null', $out, $code);
            $find = $code === 0 && !empty($out);
        }

        $phpCli = false;
        $this->phpCliCommand = '';
        if ($exec) {
            $candidates = [];
            if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
                $candidates[] = rtrim(PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . 'php';
            }
            $candidates[] = 'php';

            foreach ($candidates as $candidate) {
                $out = [];
                $code = 1;
                $cmd = ($candidate === 'php' ? 'php' : escapeshellarg($candidate)) . ' -r ' . escapeshellarg('echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;') . ' 2>/dev/null';
                @exec($cmd, $out, $code);
                if ($code === 0 && !empty($out)) {
                    $phpCli = true;
                    $this->phpCliCommand = $candidate;
                    break;
                }
            }
        }

        return [
            'exec' => $exec,
            'proc_open' => $proc,
            'find' => $find,
            'php_cli' => $phpCli,
            'system_scan_available' => $exec && $proc && $find,
            'background_worker_available' => $exec && $phpCli,
            'mode' => ($exec && $proc && $find && $phpCli) ? 'system-fast' : 'unavailable',
        ];
    }

    protected function spawnWorker($action)
    {
        $cap = $this->engineCapabilities();
        if (!$cap['background_worker_available'] || $this->phpCliCommand === '') {
            return false;
        }

        $script = $this->root . 'tools' . DIRECTORY_SEPARATOR . 'storage_worker.php';
        if (!is_file($script)) {
            return false;
        }

        $php = $this->phpCliCommand === 'php' ? 'php' : escapeshellarg($this->phpCliCommand);
        $cmd = 'nohup ' . $php . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($action) . ' ' . escapeshellarg(rtrim($this->root, '/\\')) . ' >/dev/null 2>&1 &';
        $out = [];
        $code = 0;
        @exec($cmd, $out, $code);
        return $code === 0;
    }
}
