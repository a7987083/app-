<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Area;
use app\common\model\Version;
use fast\Random;
use think\Config;

/**
 * 公共接口
 */
class Common extends Api
{
    protected $noNeedLogin = ['init', 'iconupload'];
    protected $noNeedRight = '*';

    /**
     * 加载初始化
     *
     * @param string $version 版本号
     * @param string $lng     经度
     * @param string $lat     纬度
     */
    public function init()
    {
        if ($version = $this->request->request('version')) {
            $lng = $this->request->request('lng');
            $lat = $this->request->request('lat');
            $upload = Config::get('upload');
            $uploaddata = [
                'cdnurl'    => isset($upload['cdnurl']) ? $upload['cdnurl'] : '',
                'uploadurl' => isset($upload['uploadurl']) ? $upload['uploadurl'] : 'ajax/upload',
                'maxsize'   => isset($upload['maxsize']) ? $upload['maxsize'] : '10mb',
                'mimetype'  => isset($upload['mimetype']) ? $upload['mimetype'] : '',
                'multiple'  => isset($upload['multiple']) ? $upload['multiple'] : false,
            ];
            $content = [
                'citydata'    => Area::getCityFromLngLat($lng, $lat),
                'versiondata' => Version::check($version),
                'uploaddata'  => $uploaddata,
                'coverdata'   => Config::get("cover"),
            ];
            $this->success('', $content);
        } else {
            $this->error(__('Invalid parameters'));
        }
    }


    /**
     * 服务器间 App 图标上传
     * 仅接受 PNG/JPG/JPEG，使用独立共享令牌，不依赖后台登录态。
     * @ApiMethod (POST)
     * @param File $file 图片文件
     */
    public function iconupload()
    {
        $expected = trim((string)\think\Env::get('zonoe.icon_upload_token', getenv('ZONOE_ICON_UPLOAD_TOKEN') ?: ''));
        $provided = trim((string)$this->request->server('HTTP_X_ZONOE_UPLOAD_TOKEN', ''));
        if ($expected === '') {
            $this->error('Icon upload is not configured', null, 503);
        }
        if ($provided === '' || !hash_equals($expected, $provided)) {
            $this->error('Invalid icon upload token', null, 403);
        }

        $file = $this->request->file('file');
        if (empty($file)) {
            $this->error(__('No file upload or server upload limit exceeded'));
        }

        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        if (!in_array($suffix, ['png', 'jpg', 'jpeg'], true)) {
            $this->error(__('Uploaded file format is limited'));
        }
        if ((int)$fileInfo['size'] <= 0 || (int)$fileInfo['size'] > 2 * 1024 * 1024) {
            $this->error('Icon file is too large');
        }

        // IPA 内 AppIcon 可能是 iOS 优化 PNG；这里验证文件签名而不强制 GD 解码。
        $head = @file_get_contents($fileInfo['tmp_name'], false, null, 0, 12);
        $isPng = substr((string)$head, 0, 8) === "\x89PNG\r\n\x1a\n";
        $isJpeg = substr((string)$head, 0, 3) === "\xff\xd8\xff";
        if (($suffix === 'png' && !$isPng) || (in_array($suffix, ['jpg', 'jpeg'], true) && !$isJpeg)) {
            $this->error(__('Uploaded file is not a valid image'));
        }

        $filemd5 = md5_file($fileInfo['tmp_name']);
        $uploadDir = '/uploads/' . date('Ymd') . '/';
        $fileName = $filemd5 . '.' . $suffix;
        $splInfo = $file->validate(['size' => 2 * 1024 * 1024])->move(ROOT_PATH . '/public' . $uploadDir, $fileName);
        if (!$splInfo) {
            $this->error($file->getError());
        }

        $params = [
            'admin_id'    => 0,
            'user_id'     => 0,
            'filesize'    => (int)$fileInfo['size'],
            'imagewidth'  => 0,
            'imageheight' => 0,
            'imagetype'   => $suffix,
            'imageframes' => 0,
            'mimetype'    => $fileInfo['type'],
            'url'         => $uploadDir . $splInfo->getSaveName(),
            'uploadtime'  => time(),
            'storage'     => 'local',
            'sha1'        => $file->hash(),
        ];
        $attachment = model("attachment");
        $attachment->data(array_filter($params, function ($value) {
            return $value !== null && $value !== '';
        }));
        $attachment->save();
        \think\Hook::listen("upload_after", $attachment);

        $this->success(__('Upload successful'), [
            'url' => $uploadDir . $splInfo->getSaveName(),
        ]);
    }

    /**
     * 上传文件
     * @ApiMethod (POST)
     * @param File $file 文件流
     */
    public function upload()
    {
        $file = $this->request->file('file');
        if (empty($file)) {
            $this->error(__('No file upload or server upload limit exceeded'));
        }

        //判断是否已经存在附件
        $sha1 = $file->hash();

        $upload = Config::get('upload');

        preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
        $type = strtolower($matches[2]);
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size = (int)$upload['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);
        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix = $suffix && preg_match("/^[a-zA-Z0-9]+$/", $suffix) ? $suffix : 'file';

        $mimetypeArr = explode(',', strtolower($upload['mimetype']));
        $typeArr = explode('/', $fileInfo['type']);

        //禁止上传PHP和HTML文件
        if (in_array($fileInfo['type'], ['text/x-php', 'text/html']) || in_array($suffix, ['php', 'php3', 'php4', 'php5', 'phtml', 'pht', 'phar', 'html', 'htm', 'shtml']) || substr($fileInfo['name'], 0, 1) === '.') {
            $this->error(__('Uploaded file format is limited'));
        }
        //验证文件后缀
        if ($upload['mimetype'] !== '*' &&
            (
                !in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
            )
        ) {
            $this->error(__('Uploaded file format is limited'));
        }
        //验证是否为图片文件
        $imagewidth = $imageheight = 0;
        if (in_array($fileInfo['type'], ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
            $imgInfo = getimagesize($fileInfo['tmp_name']);
            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                $this->error(__('Uploaded file is not a valid image'));
            }
            $imagewidth = isset($imgInfo[0]) ? $imgInfo[0] : $imagewidth;
            $imageheight = isset($imgInfo[1]) ? $imgInfo[1] : $imageheight;
        }
        $replaceArr = [
            '{year}'     => date("Y"),
            '{mon}'      => date("m"),
            '{day}'      => date("d"),
            '{hour}'     => date("H"),
            '{min}'      => date("i"),
            '{sec}'      => date("s"),
            '{random}'   => Random::alnum(16),
            '{random32}' => Random::alnum(32),
            '{filename}' => $suffix ? substr($fileInfo['name'], 0, strripos($fileInfo['name'], '.')) : $fileInfo['name'],
            '{suffix}'   => $suffix,
            '{.suffix}'  => $suffix ? '.' . $suffix : '',
            '{filemd5}'  => md5_file($fileInfo['tmp_name']),
        ];
        $savekey = $upload['savekey'];
        $savekey = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);

        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName = substr($savekey, strripos($savekey, '/') + 1);
        //
        $splInfo = $file->validate(['size' => $size])->move(ROOT_PATH . '/public' . $uploadDir, $fileName);
        if ($splInfo) {
            $params = array(
                'admin_id'    => 0,
                'user_id'     => (int)$this->auth->id,
                'filesize'    => $fileInfo['size'],
                'imagewidth'  => $imagewidth,
                'imageheight' => $imageheight,
                'imagetype'   => $suffix,
                'imageframes' => 0,
                'mimetype'    => $fileInfo['type'],
                'url'         => $uploadDir . $splInfo->getSaveName(),
                'uploadtime'  => time(),
                'storage'     => 'local',
                'sha1'        => $sha1,
            );
            $attachment = model("attachment");
            $attachment->data(array_filter($params));
            $attachment->save();
            \think\Hook::listen("upload_after", $attachment);
            $this->success(__('Upload successful'), [
                'url' => $uploadDir . $splInfo->getSaveName()
            ]);
        } else {
            // 上传失败获取错误信息
            $this->error($file->getError());
        }
    }
}
