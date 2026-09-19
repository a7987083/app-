<?php

namespace app\common\library;

class IpaReferenceDiscoveryService
{
    public static function collect(array $openListSource)
    {
        $sources=IpaMysqlSourceService::all(true,true);
        $refs=[];$errors=[];$ignored=0;
        foreach($sources as $source){
            try{
                foreach(IpaMysqlSourceService::readReferences($source) as $ref){
                    $apiPath=self::publicUrlToRemotePath($ref['download_url'],$openListSource);
                    if($apiPath===null){$ignored++;continue;}
                    $ref['remote_path']=$apiPath;$refs[]=$ref;
                }
            }catch(\Exception $e){$errors[]=['source_slug'=>$source['slug'],'message'=>$e->getMessage()];}
        }
        $paths=[];$dirs=[];
        foreach($refs as $ref){$path=$ref['remote_path'];$paths[$path]=true;$dir=str_replace('\\','/',dirname($path));if($dir==='.'||$dir==='')$dir='/';$dirs[IpaRemoteFile::normalizePath($dir)]=true;}
        return ['sources'=>count($sources),'refs'=>$refs,'paths'=>array_keys($paths),'directories'=>array_keys($dirs),'ignored'=>$ignored,'errors'=>$errors];
    }

    public static function publicUrlToRemotePath($rawUrl,array $config)
    {
        $rawUrl=trim((string)$rawUrl);if($rawUrl==='')return null;
        $base=parse_url((string)$config['base_url']);$url=parse_url($rawUrl);
        if(!$base||!$url||empty($base['host'])||empty($url['host'])||strcasecmp($base['host'],$url['host'])!==0)return null;
        $path=isset($url['path'])?rawurldecode($url['path']):'';if($path==='')return null;
        $template=isset($config['public_url_template'])?trim((string)$config['public_url_template']):'';$prefixPath='';
        if($template!==''){$template=str_replace('{path}','',$template);$parsed=parse_url($template);$prefixPath=$parsed&&isset($parsed['path'])?rawurldecode($parsed['path']):$template;$prefixPath=rtrim(IpaRemoteFile::normalizePath($prefixPath),'/');}
        if($prefixPath!==''&&$prefixPath!=='/'&&strpos($path,$prefixPath.'/')===0)$path=substr($path,strlen($prefixPath));
        $path=IpaRemoteFile::normalizePath($path);$root=isset($config['scan_path'])?IpaRemoteFile::normalizePath($config['scan_path']):'/';
        if($root!=='/'&&$path!==$root&&strpos($path,$root.'/')!==0)return null;
        return IpaRemoteFile::isIpaName(basename($path))?$path:null;
    }

    public static function listReferencedRemoteFiles(array $source,$forceRefresh=false)
    {
        $discovery=self::collect($source);
        if(!$discovery['directories'])return ['files'=>[],'directories'=>0,'cache_hits'=>0,'cache_refreshes'=>0,'reference'=>$discovery];
        $client=IpaSourceConfig::clientFromRow($source);$scope=IpaDirectoryCache::scopeKey($source);$ttl=isset($source['cache_ttl'])?(int)$source['cache_ttl']:1800;
        $wanted=array_fill_keys($discovery['paths'],true);$files=[];$hits=0;$refreshes=0;
        foreach($discovery['directories'] as $dir){
            $cached=$forceRefresh?null:IpaDirectoryCache::load($scope,$dir,$ttl);
            if($cached){$rows=$cached['files'];$hits++;}
            else{
                $rows=[];$page=1;$perPage=1000;$total=0;
                do{
                    // force refresh only bypasses our cache; OpenList refresh
                    // stays false exactly like the mature implementation.
                    $listed=$client->listDirectory($dir,$page,$perPage,false);$entries=(array)$listed['content'];$total=(int)$listed['total'];
                    foreach($entries as $entry){if(!is_array($entry)||!empty($entry['is_dir'])||empty($entry['name'])||!IpaRemoteFile::isIpaName($entry['name']))continue;$rows[]=IpaRemoteFile::fromOpenListEntry('openlist',$dir,$entry,$source['public_url_template']);}
                    $page++;$more=$total>0?(($page-1)*$perPage<$total):(count($entries)>=$perPage);
                }while($more);
                IpaDirectoryCache::save($scope,$dir,$rows,$total);$refreshes++;
            }
            foreach((array)$rows as $row){if(isset($wanted[$row['remote_path']]))$files[$row['remote_path']]=$row;}
        }
        return ['files'=>array_values($files),'directories'=>count($discovery['directories']),'cache_hits'=>$hits,'cache_refreshes'=>$refreshes,'reference'=>$discovery];
    }
}
