<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\library\codegen\ObjectiveCGenerator;
use app\common\library\Ipa\SecretBox;
use think\Db;

class Occodegen extends Backend
{
    protected $noNeedRight = [];

    public function index()
    {
        return redirect('dylib_center/index');
    }

    public function current()
    {
        try {
            list($generator, $config, $versions, $selectedVersionId) = $this->context(false);
            return json([
                'code' => 200,
                'msg' => 'ok',
                'data' => [
                    'config' => $generator->publicConfig($config),
                    'config_hash' => $generator->configHash($config),
                    'validation' => $generator->validate($config),
                    'versions' => $versions,
                    'selected_version_id' => $selectedVersionId,
                ],
            ]);
        } catch (\Exception $e) {
            return json(['code' => 422, 'msg' => $e->getMessage(), 'data' => '']);
        }
    }

    public function preview()
    {
        if (!$this->request->isPost()) return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        try {
            list($generator, $config, $versions, $selectedVersionId) = $this->context(true);
            $validation = $generator->validate($config);
            $files = [];
            if ($validation['valid']) {
                foreach ($generator->generate($config) as $path => $content) {
                    $files[] = ['path' => $path, 'content' => $content, 'sha256' => hash('sha256', $content)];
                }
            }
            return json([
                'code' => $validation['valid'] ? 200 : 422,
                'msg' => $validation['valid'] ? '预览已生成' : '配置校验失败',
                'data' => [
                    'config' => $generator->publicConfig($config),
                    'config_hash' => $generator->configHash($config),
                    'validation' => $validation,
                    'versions' => $versions,
                    'selected_version_id' => $selectedVersionId,
                    'files' => $files,
                ],
            ]);
        } catch (\Exception $e) {
            return json(['code' => 422, 'msg' => $e->getMessage(), 'data' => '']);
        }
    }

    public function generate()
    {
        if (!$this->request->isPost()) return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        try {
            list($generator, $config) = $this->context(true);
            $validation = $generator->validate($config);
            if (!$validation['valid']) return json(['code' => 422, 'msg' => implode('；', $validation['errors']), 'data' => ['validation' => $validation]]);

            $hash = $generator->configHash($config);
            $expected = strtolower(trim((string)$this->request->post('config_hash', '')));
            if ($expected === '' || !hash_equals($hash, $expected)) {
                return json(['code' => 409, 'msg' => 'Dylib/版本/运行配置已变化，请重新预览后再生成', 'data' => ['config_hash' => $hash]]);
            }
            if (!class_exists('ZipArchive')) throw new \RuntimeException('服务器未安装 ZipArchive，无法生成 ZIP');

            $this->cleanupExpired();
            $dir = $this->runtimeDir();
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new \RuntimeException('无法创建代码生成缓存目录');

            $token = $this->randomToken();
            $zipPath = $dir . $token . '.zip';
            $metaPath = $dir . $token . '.json';
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('无法创建 ZIP');
            foreach ($generator->generate($config) as $path => $content) $zip->addFromString('GeneratedOC/' . $path, $content);
            $zip->close();

            $safeKey = preg_replace('/[^A-Za-z0-9._-]/', '-', (string)$config['dylib_key']);
            $filename = $safeKey . '-' . $config['dylib_version'] . '-ObjectiveC-' . substr($hash, 0, 12) . '.zip';
            $meta = [
                'token' => $token,
                'filename' => $filename,
                'config_hash' => $hash,
                'sha256' => hash_file('sha256', $zipPath),
                'dylib_id' => (int)$config['dylib_id'],
                'version_id' => (int)$config['version_id'],
                'created_at' => time(),
                'expires_at' => time() + 3600,
            ];
            if (@file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
                @unlink($zipPath);
                throw new \RuntimeException('无法写入生成记录');
            }
            return json([
                'code' => 200,
                'msg' => 'Dylib OC 接入代码已生成',
                'data' => [
                    'token' => $token,
                    'filename' => $filename,
                    'sha256' => $meta['sha256'],
                    'config_hash' => $hash,
                    'download_url' => 'general/occodegen/download?token=' . rawurlencode($token),
                ],
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage(), 'data' => '']);
        }
    }

    public function download()
    {
        $token = trim((string)$this->request->get('token', ''));
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) return json(['code' => 400, 'msg' => '下载标识无效', 'data' => '']);
        $dir = $this->runtimeDir();
        $zipPath = $dir . $token . '.zip';
        $metaPath = $dir . $token . '.json';
        $meta = is_file($metaPath) ? json_decode((string)@file_get_contents($metaPath), true) : null;
        if (!is_array($meta) || !is_file($zipPath)) return json(['code' => 404, 'msg' => '生成文件不存在或已过期', 'data' => '']);
        if (empty($meta['expires_at']) || (int)$meta['expires_at'] < time()) {
            @unlink($zipPath); @unlink($metaPath);
            return json(['code' => 410, 'msg' => '生成文件已过期，请重新生成', 'data' => '']);
        }
        $filename = isset($meta['filename']) ? basename((string)$meta['filename']) : 'GeneratedOC.zip';
        $content = @file_get_contents($zipPath);
        if ($content === false) return json(['code' => 500, 'msg' => '读取生成文件失败', 'data' => '']);
        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Length' => strlen($content),
            'Content-Disposition' => 'attachment; filename="' . addcslashes($filename, '"\\') . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Generated-SHA256' => isset($meta['sha256']) ? (string)$meta['sha256'] : '',
        ]);
    }

    protected function context($post)
    {
        $source = $post ? $_POST : $_GET;
        $dylibId = isset($source['dylib_id']) ? (int)$source['dylib_id'] : 0;
        $versionId = isset($source['version_id']) ? (int)$source['version_id'] : 0;
        if ($dylibId <= 0) throw new \InvalidArgumentException('请选择 Dylib');

        $dylib = Db::name('dylib')->where('id', $dylibId)->find();
        if (!$dylib) throw new \InvalidArgumentException('Dylib 不存在');
        $ciphertext = isset($dylib['verify_secret_ciphertext']) ? (string)$dylib['verify_secret_ciphertext'] : '';
        $dylib['verify_secret'] = SecretBox::decrypt($ciphertext);

        $runtimeConfig = Db::name('dylib_runtime_config')->where('id', 1)->find();
        if (!$runtimeConfig) {
            $runtimeConfig = [
                'config_version' => 1,
                'api_endpoints_json' => '[]',
                'bootstrap_urls_json' => '[]',
                'verify_path' => '/index/dylib_verify/verify',
            ];
        }

        $versions = Db::name('dylib_version')
            ->where('dylib_id', $dylibId)
            ->field('id,version,build,state,sha256,offline_grace,fail_action,notice,created_at,updated_at')
            ->order('id desc')->select();
        $selected = null;
        if ($versionId > 0) {
            foreach ($versions as $row) if ((int)$row['id'] === $versionId) { $selected = $row; break; }
        }
        if (!$selected) {
            foreach ($versions as $row) if ((string)$row['state'] === 'active') { $selected = $row; break; }
        }
        if (!$selected) {
            foreach ($versions as $row) if ((string)$row['state'] === 'testing') { $selected = $row; break; }
        }
        if (!$selected && $versions) $selected = $versions[0];
        if (!$selected) $selected = ['id'=>0,'version'=>'','build'=>'','state'=>'','sha256'=>'','offline_grace'=>(int)$dylib['default_offline_grace'],'fail_action'=>(string)$dylib['default_fail_action'],'notice'=>''];

        $draft = [
            'class_prefix' => isset($source['class_prefix']) ? $source['class_prefix'] : 'ZON',
            'deployment_target' => isset($source['deployment_target']) ? $source['deployment_target'] : '13.0',
            'timeout' => isset($source['timeout']) ? $source['timeout'] : 10,
        ];
        $generator = new ObjectiveCGenerator();
        $config = $generator->buildConfig($dylib, $runtimeConfig, $selected, $draft, $this->request->domain());
        return [$generator, $config, $versions, (int)$selected['id']];
    }

    protected function runtimeDir()
    {
        return rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'codegen' . DIRECTORY_SEPARATOR;
    }

    protected function randomToken()
    {
        if (function_exists('random_bytes')) return bin2hex(random_bytes(16));
        return md5(uniqid('', true) . mt_rand());
    }

    protected function cleanupExpired()
    {
        $dir = $this->runtimeDir();
        if (!is_dir($dir)) return;
        $cutoff = time() - 7200;
        foreach ((array)glob($dir . '*') as $path) if (is_file($path) && @filemtime($path) < $cutoff) @unlink($path);
    }
}
