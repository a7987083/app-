<?php

namespace app\common\library;

use think\Config;
use think\Db;
use RuntimeException;

/** Durable registry for external MySQL software sources.
 *
 * This service only owns connection metadata.  It never enables writeback;
 * writeback remains governed by the existing binding/writeback subsystem.
 */
class IpaMysqlSourceService
{
    public static function all($withSecret=false,$enabledOnly=false)
    {
        $query=Db::name('ipa_mysql_source');
        if($enabledOnly)$query->where('enabled',1);
        $rows=$query->order('priority','asc')->order('id','asc')->select();
        $out=[];
        foreach((array)$rows as $row)$out[]=self::publicRow($row,$withSecret);
        return $out;
    }

    public static function get($id,$withSecret=false)
    {
        $row=Db::name('ipa_mysql_source')->where('id',(int)$id)->find();
        if(!$row)throw new RuntimeException('MySQL 软件源不存在');
        return self::publicRow($row,$withSecret);
    }

    public static function save(array $input,$adminId=0)
    {
        $id=isset($input['id'])?(int)$input['id']:0;
        $existing=$id>0?Db::name('ipa_mysql_source')->where('id',$id)->find():null;
        if($id>0&&!$existing)throw new RuntimeException('MySQL 软件源不存在');
        $name=trim((string)(isset($input['name'])?$input['name']:''));
        $slug=strtolower(trim((string)(isset($input['slug'])?$input['slug']:'')));
        $host=trim((string)(isset($input['host'])?$input['host']:'127.0.0.1'));
        $port=(int)(isset($input['port'])?$input['port']:3306);
        $database=trim((string)(isset($input['database'])?$input['database']:(isset($input['database_name'])?$input['database_name']:'')));
        $username=trim((string)(isset($input['username'])?$input['username']:''));
        $password=(string)(isset($input['password'])?$input['password']:'');
        $table=trim((string)(isset($input['table'])?$input['table']:(isset($input['table_name'])?$input['table_name']:'fa_category')));
        $priority=(int)(isset($input['priority'])?$input['priority']:100);
        $enabled=!empty($input['enabled'])?1:0;

        if($name==='')throw new RuntimeException('名称不能为空');
        if(!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/',$slug))throw new RuntimeException('Slug 仅允许小写字母、数字、下划线和短横线');
        if($host==='')throw new RuntimeException('MySQL Host 不能为空');
        if($port<1||$port>65535)throw new RuntimeException('MySQL Port 无效');
        if($database===''||$username==='')throw new RuntimeException('Database 和 Username 不能为空');
        if(!self::safeIdentifier($table))throw new RuntimeException('应用表名称无效');
        if($password===''&&$existing&&!empty($existing['password_ciphertext']))$password=self::openSecret($existing['password_ciphertext']);
        if($password===''&&!$existing)throw new RuntimeException('新增 MySQL 软件源必须填写 Password');
        $duplicate=Db::name('ipa_mysql_source')->where('slug',$slug);
        if($id>0)$duplicate->where('id','<>',$id);
        if($duplicate->find())throw new RuntimeException('Slug 已存在');

        $now=time();
        $row=[
            'name'=>$name,'slug'=>$slug,'host'=>$host,'port'=>$port,'database_name'=>$database,'username'=>$username,
            'password_ciphertext'=>self::sealSecret($password),'table_name'=>$table,'priority'=>$priority,'enabled'=>$enabled,
            'admin_id'=>(int)$adminId,'updatetime'=>$now,
        ];
        if($existing){
            Db::name('ipa_mysql_source')->where('id',$id)->update($row);
            return $id;
        }
        $row['createtime']=$now;
        return (int)Db::name('ipa_mysql_source')->insertGetId($row);
    }

    public static function delete($id)
    {
        $id=(int)$id;
        if($id<=0)throw new RuntimeException('MySQL 软件源 ID 无效');
        // This intentionally deletes configuration only.  No remote SQL is run.
        return (int)Db::name('ipa_mysql_source')->where('id',$id)->delete();
    }

    public static function test($id)
    {
        $source=self::get($id,true);
        $started=microtime(true);
        try{
            $db=self::connect($source);
            $db->execute('SELECT 1');
            $table=self::safeIdentifier($source['table_name'])?$source['table_name']:'fa_category';
            $db->query('SELECT 1 FROM `'.$table.'` LIMIT 1');
            $elapsed=(int)round((microtime(true)-$started)*1000);
            self::recordHealth($id,'ok','');
            return ['connected'=>true,'latency_ms'=>$elapsed,'table'=>$table];
        }catch(\Exception $e){
            self::recordHealth($id,'failed',$e->getMessage());
            throw new RuntimeException('MySQL 连接失败：'.$e->getMessage());
        }
    }

    public static function readReferences(array $source)
    {
        $table=isset($source['table_name'])?$source['table_name']:'fa_category';
        if(!self::safeIdentifier($table))throw new RuntimeException('应用表名称无效：'.$table);
        $db=self::connect($source);
        // Keep the mature source-manager semantics: only active rows with a
        // non-empty bt1a download URL participate in IPA discovery.
        $sql="SELECT id,name,nickname,bt1a,bt2a FROM `{$table}` WHERE status IN ('normal','published','1','active') AND bt1a IS NOT NULL AND bt1a<>''";
        $rows=$db->query($sql);
        $refs=[];
        foreach((array)$rows as $row){
            $refs[]=[
                'source_id'=>(int)$source['id'],'source_slug'=>(string)$source['slug'],'source_name'=>(string)$source['name'],
                'legacy_id'=>isset($row['id'])?(int)$row['id']:0,'name'=>isset($row['name'])?(string)$row['name']:'',
                'version'=>isset($row['nickname'])?(string)$row['nickname']:'','download_url'=>isset($row['bt1a'])?trim((string)$row['bt1a']):'',
                'db_size'=>isset($row['bt2a'])?(int)$row['bt2a']:0,
            ];
        }
        return $refs;
    }

    protected static function connect(array $source)
    {
        return Db::connect([
            'type'=>'mysql','hostname'=>$source['host'],'hostport'=>(int)$source['port'],'database'=>$source['database_name'],
            'username'=>$source['username'],'password'=>$source['password'],'prefix'=>'','charset'=>'utf8','debug'=>false,
        ],true);
    }

    protected static function publicRow(array $row,$withSecret)
    {
        $out=$row;
        $out['database']=isset($row['database_name'])?$row['database_name']:'';
        $out['table']=isset($row['table_name'])?$row['table_name']:'fa_category';
        $out['password_configured']=!empty($row['password_ciphertext']);
        if($withSecret)$out['password']=self::openSecret(isset($row['password_ciphertext'])?$row['password_ciphertext']:'');
        unset($out['password_ciphertext']);
        return $out;
    }

    protected static function recordHealth($id,$health,$error)
    {
        Db::name('ipa_mysql_source')->where('id',(int)$id)->update(['last_health'=>(string)$health,'last_error'=>mb_substr((string)$error,0,500,'UTF-8'),'last_checked_at'=>time(),'updatetime'=>time()]);
    }

    public static function safeIdentifier($value)
    {
        return preg_match('/^[A-Za-z0-9_]+$/',(string)$value)===1;
    }

    protected static function sealSecret($plain)
    {
        $plain=(string)$plain;if($plain==='')return '';
        if(!function_exists('openssl_encrypt'))throw new RuntimeException('OpenSSL extension is required');
        $key=self::secretKey();$iv=random_bytes(16);$cipher=openssl_encrypt($plain,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);
        if($cipher===false)throw new RuntimeException('MySQL Password 加密失败');
        $mac=hash_hmac('sha256',$iv.$cipher,$key,true);
        return 'v1:'.base64_encode($iv.$mac.$cipher);
    }

    protected static function openSecret($sealed)
    {
        $sealed=trim((string)$sealed);if($sealed==='')return '';
        if(strpos($sealed,'v1:')!==0||!function_exists('openssl_decrypt'))throw new RuntimeException('MySQL Password 密文格式无效');
        $raw=base64_decode(substr($sealed,3),true);if($raw===false||strlen($raw)<49)throw new RuntimeException('MySQL Password 密文无效');
        $iv=substr($raw,0,16);$mac=substr($raw,16,32);$cipher=substr($raw,48);$key=self::secretKey();
        if(!hash_equals(hash_hmac('sha256',$iv.$cipher,$key,true),$mac))throw new RuntimeException('MySQL Password 密文完整性校验失败');
        $plain=openssl_decrypt($cipher,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);
        if($plain===false)throw new RuntimeException('MySQL Password 解密失败');
        return $plain;
    }

    protected static function secretKey()
    {
        $env=getenv('IPA_CONFIG_KEY');if($env!==false&&strlen(trim($env))>=16)return hash('sha256','mysql-source|'.trim($env),true);
        $host=(string)Config::get('database.hostname');$name=(string)Config::get('database.database');
        $user=(string)Config::get('database.username');$password=(string)Config::get('database.password');
        return hash('sha256','phase20-mysql-source|'.$host.'|'.$name.'|'.$user.'|'.$password,true);
    }
}
