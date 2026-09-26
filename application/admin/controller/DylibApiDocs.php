<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\DylibApiDocumentation;
use think\Db;

/**
 * Download-only API documentation endpoint for the Dylib Center.
 * Real Verify Secrets are intentionally never exported.
 */
class DylibApiDocs extends Backend
{
    protected $noNeedRight = ['download'];

    public function download()
    {
        $dylibId = (int)$this->request->get('dylib_id', 0);
        if ($dylibId <= 0) {
            $this->error('请选择 Dylib');
        }

        $dylib = Db::name('dylib')->field('id,dylib_key,name')->where('id', $dylibId)->find();
        if (!$dylib) {
            $this->error('Dylib 不存在');
        }

        $runtime = Db::name('dylib_runtime_config')->where('id', 1)->find();
        if (!$runtime) {
            $runtime = [
                'config_version' => 1,
                'verify_path' => '/index/dylib_verify/verify',
            ];
        }
        $verifyPath = isset($runtime['verify_path']) ? trim((string)$runtime['verify_path']) : '/index/dylib_verify/verify';
        if ($verifyPath === '' || $verifyPath[0] !== '/') {
            $verifyPath = '/index/dylib_verify/verify';
        }

        if (!class_exists('ZipArchive')) {
            $this->error('服务器缺少 ZipArchive 扩展，无法生成文档 ZIP');
        }

        $files = DylibApiDocumentation::exportFiles(
            (string)$dylib['dylib_key'],
            (string)$dylib['name'],
            $verifyPath,
            $runtime
        );

        $safeKey = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$dylib['dylib_key']);
        $fileName = 'Dylib-API-Integration-' . ($safeKey !== '' ? $safeKey : $dylibId) . '.zip';
        $tmp = tempnam(sys_get_temp_dir(), 'zonoe-api-docs-');
        if ($tmp === false) {
            $this->error('无法创建临时文件');
        }
        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            $this->error('无法创建文档 ZIP');
        }
        foreach ($files as $path => $content) {
            $zip->addFromString((string)$path, (string)$content);
        }
        $zip->close();

        if (!is_file($zipPath)) {
            $this->error('文档 ZIP 生成失败');
        }

        if (ob_get_level()) {
            @ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: private, no-store, max-age=0');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }
}
