<?php

namespace app\common\library\Ipa;

/** Minimal OpenList API client used by IPA Data Center. */
class OpenListClient
{
    const DIRECTORY_CACHE_TTL = 1800;

    protected $baseUrl;
    protected $token;
    protected $timeout;

    public function __construct($baseUrl,$token='',$timeout=20)
    {
        $this->baseUrl=rtrim((string)$baseUrl,'/');$this->token=trim((string)$token);$this->timeout=max(3,(int)$timeout);
    }

    public function listDirectory($path,$page=1,$perPage=500,$refresh=false)
    {
        $perPage=(int)$perPage;
        if($perPage!==0)$perPage=max(1,min(1000,$perPage));
        $payload=['path'=>$this->normalizePath($path),'password'=>'','page'=>max(1,(int)$page),'per_page'=>$perPage,'refresh'=>(bool)$refresh];

        // Mirror ipaxiazaizhan-'s 30-minute OpenList directory cache. The cache is
        // persistent across FPM/CLI processes and scoped by endpoint + token +
        // normalized request, so repeated scans do not keep listing unchanged dirs.
        if(!$refresh){
            $cached=$this->readDirectoryCache($payload);
            if($cached!==null)return $cached;
        }

        $data=$this->post('/api/fs/list',$payload);
        $this->writeDirectoryCache($payload,$data);
        return $data;
    }

    public function getFile($path)
    {
        return $this->post('/api/fs/get',['path'=>$this->normalizePath($path),'password'=>'']);
    }

    protected function post($endpoint,array $payload)
    {
        $ch=curl_init($this->baseUrl.$endpoint);if($ch===false)throw new \RuntimeException('无法初始化 curl');
        $headers=['Content-Type: application/json','Accept: application/json'];if($this->token!=='')$headers[]='Authorization: '.$this->token;
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>min(10,$this->timeout),CURLOPT_TIMEOUT=>$this->timeout,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $body=curl_exec($ch);$errno=curl_errno($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($body===false||$errno!==0)throw new \RuntimeException('OpenList 请求失败：'.$error,$errno);
        if($status<200||$status>=300)throw new \RuntimeException('OpenList HTTP '.$status);
        $decoded=json_decode($body,true);if(!is_array($decoded))throw new \RuntimeException('OpenList 返回的 JSON 无效');
        $code=isset($decoded['code'])?(int)$decoded['code']:0;if($code!==200){$msg=isset($decoded['message'])?(string)$decoded['message']:'未知错误';throw new \RuntimeException('OpenList API 错误：'.$msg,$code);}return isset($decoded['data'])&&is_array($decoded['data'])?$decoded['data']:[];
    }

    protected function readDirectoryCache(array $payload)
    {
        $file=$this->directoryCacheFile($payload);
        if(!is_file($file))return null;
        $mtime=@filemtime($file);
        if(!$mtime||$mtime<time()-self::DIRECTORY_CACHE_TTL)return null;
        $raw=@file_get_contents($file);
        if($raw===false||$raw==='')return null;
        $decoded=json_decode($raw,true);
        if(!is_array($decoded)||!isset($decoded['data'])||!is_array($decoded['data']))return null;
        return $decoded['data'];
    }

    protected function writeDirectoryCache(array $payload,array $data)
    {
        $file=$this->directoryCacheFile($payload);
        $dir=dirname($file);
        if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))return;
        $json=json_encode(['fetched_at'=>time(),'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if($json===false)return;
        $tmp=$file.'.'.getmypid().'.tmp';
        if(@file_put_contents($tmp,$json,LOCK_EX)!==false){
            @rename($tmp,$file);
        }else{
            @unlink($tmp);
        }
    }

    protected function directoryCacheFile(array $payload)
    {
        $root=defined('RUNTIME_PATH')?RUNTIME_PATH:(dirname(__DIR__,4).DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR);
        $scope=$this->baseUrl.'|'.hash('sha256',$this->token).'|'.json_encode([
            'path'=>isset($payload['path'])?$payload['path']:'/',
            'page'=>isset($payload['page'])?(int)$payload['page']:1,
            'per_page'=>isset($payload['per_page'])?(int)$payload['per_page']:500,
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return rtrim($root,'/\\').DIRECTORY_SEPARATOR.'ipa-directory-cache'.DIRECTORY_SEPARATOR.hash('sha256',$scope).'.json';
    }

    protected function normalizePath($path)
    {
        $path=trim((string)$path);if($path===''||$path==='/')return '/';return '/'.ltrim(preg_replace('#/+#','/',$path),'/');
    }
}
