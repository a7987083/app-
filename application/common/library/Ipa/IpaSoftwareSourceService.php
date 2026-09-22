<?php

namespace app\common\library\Ipa;

use PDO;
use think\Db;

class IpaSoftwareSourceService
{
    public static function listSources($withSecret = false)
    {
        $rows = Db::name('ipa_software_source')->order('priority desc,id asc')->select();
        foreach ($rows as &$row) {
            if (!$withSecret) unset($row['password_ciphertext']);
            $row['password_configured'] = !empty($row['password_ciphertext']);
        }
        unset($row);
        return $rows;
    }

    public static function save(array $input)
    {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $name = trim((string)$input['name']);
        $slug = strtolower(trim((string)$input['slug']));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $host = trim((string)$input['host']);
        $database = trim((string)$input['database_name']);
        $username = trim((string)$input['username']);
        $table = trim((string)$input['table_name']);
        if ($name === '' || $slug === '' || $host === '' || $database === '' || $username === '') throw new \InvalidArgumentException('软件源必填字段不能为空');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $database) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) throw new \InvalidArgumentException('Database / 表名只能包含字母、数字和下划线');
        $dupe = Db::name('ipa_software_source')->where('slug', $slug);
        if ($id > 0) $dupe->where('id', '<>', $id);
        if ($dupe->count()) throw new \RuntimeException('软件源 Slug 已存在');
        $now = time();
        $data = [
            'name'=>$name,'slug'=>$slug,'host'=>$host,'port'=>max(1,min(65535,(int)$input['port'])),
            'database_name'=>$database,'username'=>$username,'table_name'=>$table ?: 'fa_category',
            'priority'=>(int)$input['priority'],'enabled'=>!empty($input['enabled'])?1:0,
            'allow_write'=>!empty($input['allow_write'])?1:0,'updated_at'=>$now,
        ];
        $password = isset($input['password']) ? (string)$input['password'] : '';
        if ($password !== '') $data['password_ciphertext'] = SecretBox::encrypt($password);
        if ($id > 0) {
            if (!Db::name('ipa_software_source')->where('id',$id)->find()) throw new \InvalidArgumentException('软件源不存在');
            Db::name('ipa_software_source')->where('id',$id)->update($data);
        } else {
            if ($password === '') throw new \InvalidArgumentException('新增软件源必须填写密码');
            $data['created_at']=$now;
            $id = Db::name('ipa_software_source')->insertGetId($data);
        }
        return (int)$id;
    }

    public static function delete($id)
    {
        $id=(int)$id;
        Db::startTrans();
        try {
            Db::name('ipa_compare_result')->where('software_source_id',$id)->delete();
            Db::name('ipa_software_source')->where('id',$id)->delete();
            Db::commit();
        } catch (\Exception $e) { Db::rollback(); throw $e; }
    }

    public static function test($id)
    {
        $source = self::get($id, true);
        $pdo = self::pdo($source);
        $table = self::quoteIdentifier($source['table_name']);
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM '.$table);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['rows'=>(int)$row['c']];
    }

    public static function get($id, $withSecret = false)
    {
        $row=Db::name('ipa_software_source')->where('id',(int)$id)->find();
        if (!$row) throw new \InvalidArgumentException('软件源不存在');
        if (!$withSecret) unset($row['password_ciphertext']);
        return $row;
    }

    public static function enabledSources()
    {
        return Db::name('ipa_software_source')->where('enabled',1)->order('priority desc,id asc')->select();
    }

    public static function pdo(array $source)
    {
        $password = empty($source['password_ciphertext']) ? '' : SecretBox::decrypt($source['password_ciphertext']);
        $dsn = 'mysql:host='.$source['host'].';port='.(int)$source['port'].';dbname='.$source['database_name'].';charset=utf8mb4';
        return new PDO($dsn,(string)$source['username'],$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
    }

    public static function quoteIdentifier($name)
    {
        $name=(string)$name;
        if (!preg_match('/^[A-Za-z0-9_]+$/',$name)) throw new \InvalidArgumentException('非法 SQL 标识符');
        return '`'.$name.'`';
    }
}
