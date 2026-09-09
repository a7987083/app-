<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\library\Email;
use app\common\model\Config as ConfigModel;
use think\Exception;
use think\Validate;
use think\Db;

/**
 * 系统配置
 *
 * @icon   fa fa-cogs
 * @remark 可以在此增改系统的变量和分组,也可以自定义分组和变量,如果需要删除请从数据库中删除
 */
class Config extends Backend
{

    /**
     * @var \app\common\model\Config
     */
    protected $model = null;
    protected $noNeedRight = ['check', 'rulelist'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Config');
        ConfigModel::event('before_write', function ($row) {
            if (isset($row['name']) && $row['name'] == 'name' && preg_match("/fast" . "admin/i", $row['value'])) {
                throw new Exception(__("Site name incorrect"));
            }
        });
    }

    /**
     * 查看
     */
    public function index()
    {
        $siteList = [];
        $groupList = ConfigModel::getGroupList();
        foreach ($groupList as $k => $v) {
            $siteList[$k]['name'] = $k;
            $siteList[$k]['title'] = $v;
            $siteList[$k]['list'] = [];
        }

        foreach ($this->model->all() as $k => $v) {
            if (!isset($siteList[$v['group']])) {
                continue;
            }
            $value = $v->toArray();
            $value['title'] = __($value['title']);
            if (in_array($value['type'], ['select', 'selects', 'checkbox', 'radio'])) {
                $value['value'] = explode(',', $value['value']);
            }
            $value['content'] = json_decode($value['content'], true);
            $value['tip'] = htmlspecialchars($value['tip']);
            $siteList[$v['group']]['list'][] = $value;
        }
        $index = 0;
        foreach ($siteList as $k => &$v) {
            $v['active'] = !$index ? true : false;
            $index++;
        }
        $this->view->assign('siteList', $siteList);
        $this->view->assign('typeList', ConfigModel::getTypeList());
        $this->view->assign('ruleList', ConfigModel::getRegexList());
        $this->view->assign('groupList', ConfigModel::getGroupList());
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a", [], 'trim');
            if ($params) {
                foreach ($params as $k => &$v) {
                    $v = is_array($v) ? implode(',', $v) : $v;
                }
                if (in_array($params['type'], ['select', 'selects', 'checkbox', 'radio', 'array'])) {
                    $params['content'] = json_encode(ConfigModel::decode($params['content']), JSON_UNESCAPED_UNICODE);
                } else {
                    $params['content'] = '';
                }
                try {
                    $result = $this->model->create($params);
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    try {
                        $this->refreshFile();
                    } catch (Exception $e) {
                        $this->error($e->getMessage());
                    }
                    $this->success();
                } else {
                    $this->error($this->model->getError());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     * @param null $ids
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
            $row = $this->request->post("row/a", [], 'trim');
            if ($row) {
                $configList = [];
                foreach ($this->model->all() as $v) {
                    if (isset($row[$v['name']])) {
                        $value = $row[$v['name']];
                        if (is_array($value) && isset($value['field'])) {
                            $value = json_encode(ConfigModel::getArrayData($value), JSON_UNESCAPED_UNICODE);
                        } else {
                            $value = is_array($value) ? implode(',', $value) : $value;
                        }
                        $v['value'] = $value;
                        $configList[] = $v->toArray();
                    }
                }
                try {
                    $this->model->allowField(true)->saveAll($configList);
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
                try {
                    $this->refreshFile();
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
    }

    /**
     * 删除
     * @param string $ids
     */
    public function del($ids = "")
    {
        $name = $this->request->post('name');
        $config = ConfigModel::getByName($name);
        if ($name && $config) {
            try {
                $config->delete();
                $this->refreshFile();
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success();
        } else {
            $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 刷新配置文件
     */
    protected function refreshFile()
    {
        $config = [];
        foreach ($this->model->all() as $k => $v) {
            $value = $v->toArray();
            if (in_array($value['type'], ['selects', 'checkbox', 'images', 'files'])) {
                $value['value'] = explode(',', $value['value']);
            }
            if ($value['type'] == 'array') {
                $value['value'] = (array)json_decode($value['value'], true);
            }
            $config[$value['name']] = $value['value'];
        }
        file_put_contents(
            APP_PATH . 'extra' . DS . 'site.php',
            '<?php' . "\n\nreturn " . var_export($config, true) . ";"
        );
    }

    /**
     * 检测配置项是否存在
     * @internal
     */
    public function check()
    {
        $params = $this->request->post("row/a");
        if ($params) {
            $config = $this->model->get($params);
            if (!$config) {
                return $this->success();
            } else {
                return $this->error(__('Name already exist'));
            }
        } else {
            return $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 规则列表
     * @internal
     */
    public function rulelist()
    {
        //主键
        $primarykey = $this->request->request("keyField");
        //主键值
        $keyValue = $this->request->request("keyValue", "");

        $keyValueArr = array_filter(explode(',', $keyValue));
        $regexList = \app\common\model\Config::getRegexList();
        $list = [];
        foreach ($regexList as $k => $v) {
            if ($keyValueArr) {
                if (in_array($k, $keyValueArr)) {
                    $list[] = ['id' => $k, 'name' => $v];
                }
            } else {
                $list[] = ['id' => $k, 'name' => $v];
            }
        }
        return json(['list' => $list]);
    }

    /**
     * 发送测试邮件
     * @internal
     */
    public function emailtest()
    {
        $row = $this->request->post('row/a');
        $receiver = $this->request->post("receiver");
        if ($receiver) {
            if (!Validate::is($receiver, "email")) {
                $this->error(__('Please input correct email'));
            }
            \think\Config::set('site', array_merge(\think\Config::get('site'), $row));
            $email = new Email;
            $result = $email
                ->to($receiver)
                ->subject(__("This is a test mail"))
                ->message('<div style="min-height:550px; padding: 100px 55px 200px;">' . __('This is a test mail content') . '</div>')
                ->send();
            if ($result) {
                $this->success();
            } else {
                $this->error($email->getError());
            }
        } else {
            return $this->error(__('Invalid parameters'));
        }
    }
    //检查更新
    public function update(){
        // 打开远程版本记录文件比对本地记录文件
        // 设定目录
        $local_dir = ROOT_PATH . 'ver.json';
        // 本地版本
        $local = $this->get_file($local_dir);
        if ($local === false) {
            $result= [
                'code'=>406,
                'msg'=>'本地版本记录文件获取失败',
                'data'=>''
            ];
        } else {
            $arrContextOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ];
            // 访问服务器获取最新版号  地址上线后根据域名改变
            $last_version_res = file_get_contents('https://update-appstore.nuosike.com/update/server/last_version', false, stream_context_create($arrContextOptions));
            $last_version_res = json_decode($last_version_res);
            if ($last_version_res === false) {
                $result= [
                    'code'=>406,
                    'msg'=>'服务器最新版号接口获取失败',
                    'data'=>''
                ];
            }elseif($last_version_res->code == 204 && $last_version_res->data === false){
                $result= [
                    'code'=>204,
                    'msg'=>'未获取到版号信息',
                    'data'=>''
                ];
            }else {
                // 最新版本
                $last_version = $last_version_res->data;

                // 比较版本
                $data = [
                    'last_version' =>$last_version,
                ];

                if (intval($last_version) > intval($local->version)) {
                    $result= [
                        'code'=>200,
                        'msg'=>'服务器有新版本',
                        'data'=>$data
                    ];
                } else {
                    $result= [
                        'code'=>204,
                        'msg'=>'已经是最新版本',
                        'data'=>$data
                    ];
                }

            }
        }

        return json($result);
    }
    public function get_file($url){
        if (trim($url) == '') {
            return false;
        }
        $opts = array(
          'http'=>array(
            'method'=>"GET",
            'timeout'=>3,//单位秒
           )
        );
        $cnt=0;
        while($cnt<3 && ($res=@file_get_contents($url, false, stream_context_create($opts)))===FALSE) $cnt++;
        if ($res === false) {
            return false;
        } else {
            return json_decode($res);
        }
    }
    public function dataRequest($url,$https=true,$method='get',$data=null){
        if (trim($url) == '') {
            return false;
        }

        //初始化curl
        $ch = curl_init($url);
        //字符串不直接输出，进行一个变量的存储
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//执行结果是否被返回，0是返回，1是不返回
        //https请求
        if ($https === true) {
            //确保https请求能够请求成功
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
        }
        //post请求
        if ($method == 'post') {
            curl_setopt($ch,CURLOPT_POST,true);
            curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
        }
        //发送请求
        $str = curl_exec($ch);
        $aStatus = curl_getinfo($ch);

        //关闭连接
        curl_close($ch);
        if(intval($aStatus["http_code"])==200){
            // json数据处理
            return json_decode($str);
            // return $str;
        }else{
            return false;
        }
    }
     public function get_fileres($url){
        if (trim($url) == '') {
            return false;
        }
        $opts = array(
          'http'=>array(
            'method'=>"GET",
            'timeout'=>3,//单位秒
           )
        );
         $arrContextOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ];
        $cnt=0;
        while($cnt<3 && ($res=@file_get_contents($url,  false, stream_context_create($arrContextOptions)))===FALSE) $cnt++;
        if ($res === false) {
            return false;
        } else {
            return $res;
        }
    }
    //开始更新程序
    public function system_update(){
        // 有效期内 开始更新
        // 设定目录
        // 根目录
        $base_dir = ROOT_PATH;
        // 服务器更新路径
        $update_res = 'https://update-appstore.nuosike.com/update/';
        // 本地更新路径
        $local_up_dir = $base_dir.'public/update/';
        // 本地缓存路径
        $path = $base_dir . 'public/update/cache';
        // 没有就创建
        if(!is_dir($path)){
            mkdir(iconv("UTF-8", "GBK", $path),0777,true);
        }
        // 设定缓存目录名称
        $cache_dir = $path.'/';


        // 看看需要下载几个版本的压缩包
        // 服务器更新日志存放路径
        $server = $this->get_fileres($update_res.'up_log.txt');
        if ($server === false) {
            $result = [
                'code'=>406,
                'msg'=>'服务器更新日志获取失败',
                'data'=>''
            ];
        }else{
            // 版本记录
            $server = explode(",", $server);
            $local = $this->get_fileres($local_up_dir.'ver.txt');
            if ($local === false) {
                $result = [
                    'code'=>406,
                    'msg'=>'本地更新日志获取失败',
                    'data'=>''
                ];
            } else {
                // 循环比较是否需要下载 更新
                foreach ($server as $key => $value) {
                    if (intval($local) < intval($value)) {
                        // 获取更新信息
                        // 服务器各个程序包日志存放路径
                        $up_info = $this->get_fileres($update_res.$value.'/version.json');
                        // 判断是否存在
                        if ($up_info === false) {
                            $result = [
                                'code'=>406,
                                'msg'=>'服务器更新包不存在',
                                'data'=>''
                            ];
                        } else {
                            // 信息以json格式存储便于增减和取值 故解析json对象
                            $up_info = json_decode($up_info);

                            // 下载文件
                            $back = $this->down_file($up_info->download,$cache_dir);
                            if (empty($back)) {
                                $result = [
                                    'code'=>406,
                                    'msg'=>'升级程序包下载失败',
                                    'data'=>''
                                ];
                            } else {
                                //下载成功 解压缩
                                $zip_res = $this->deal_zip($back['save_path'] ,$cache_dir);

                                // 判断解压是否成功
                                if ($zip_res == 406) {
                                    $result = [
                                        'code'=>406,
                                        'msg'=>'文件解压缩失败',
                                        'data'=>''
                                    ];
                                } else {
                                    // 开始更新数据库和文件

                                    // sql文件
                                    //读取文件内容遍历执行sql
                                    $sql_res = $this->carry_sql($cache_dir.'mysql/');
                                    
                                    if ($sql_res === false) {
                                        $result = [
                                            'code'=>406,
                                            'msg'=>'sql文件写入失败',
                                            'data'=>''
                                        ];
                                    } else {
                                        // php文件合并 返回处理的文件数
                                        $file_up_res = $this->copy_merge($cache_dir.'program/',$base_dir);
                                        if (empty($file_up_res)) {
                                            $result = [
                                                'code'=>406,
                                                'msg'=>'文件移动合并失败',
                                                'data'=>''
                                            ];
                                        }else{
                                            // 更新完改写网站本地版号
                                            $write_res = file_put_contents($local_up_dir . 'ver.txt', $value);
                                            $json['version'] = $value;
                                            file_put_contents(ROOT_PATH . 'ver.json',json_encode($json));
                                            if (empty($write_res)) {
                                                $result = [
                                                    'code'=>406,
                                                    'msg'=>'本地更新日志改写失败',
                                                    'data'=>''
                                                ];
                                            }else{
                                                // 删除临时文件
                                                $del_res = $this->deldir($cache_dir);
                                                if (empty($del_res)) {
                                                    $result = [
                                                        'code'=>406,
                                                        'msg'=>'更新缓存文件删除失败',
                                                        'data'=>''
                                                    ];
                                                }else{
                                                    $result = [
                                                        'code'=>200,
                                                        'msg'=>'在线升级已完成',
                                                        'data'=>''
                                                    ];
                                                }
                                            }
                                        }
                                    }
                                }

                            }
                        }

                    }else{
                        $result = [
                            'code'=>406,
                            'msg'=>'本地已经是最新版',
                            'data'=>''
                        ];
                    }

                }
            }

        }
        return json($result);
    }
    public function deal_zip($file,$todir)
    {
        if (trim($file) == '') {
            return 406;
        }
        if (trim($todir) == '') {
            return 406;
        }
        $zip = new \ZipArchive;
        // 中文文件名要使用ANSI编码的文件格式
        if ($zip->open($file) === TRUE) {
            //提取全部文件
            $zip->extractTo($todir);
            $zip->close();
            $result = 200;
        } else {
            $result = 406;
        }
        return $result;
    }

    /**
     * 遍历当前目录不包含下级目录
     * @param $dir 要遍历的目录
     * @param $file 要过滤的文件
     * @return str 包含所有文件及目录的数组
     */
    public function scan_dir($dir,$file='')
    {
        if (trim($dir) == '') {
            return false;
        }
        $file_arr = scandir($dir);
        $new_arr = [];
        foreach($file_arr as $item){

            if($item!=".." && $item !="." && $item != $file){
                $new_arr[] = $item;
            }
        }
        return $new_arr;

    }


    /**
     * 合并目录且只覆盖不一致的文件
     * @param $source 要合并的文件夹
     * @param $target 要合并的目的地
     * @return int 处理的文件数
     */
    public function copy_merge($source, $target) {
        if (trim($source) == '') {
            return false;
        }
        if (trim($target) == '') {
            return false;
        }
        // 路径处理
        $source = preg_replace ( '#/\\\\#', DIRECTORY_SEPARATOR, $source );
        $target = preg_replace ( '#\/#', DIRECTORY_SEPARATOR, $target );
        $source = rtrim ( $source, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
        $target = rtrim ( $target, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
        // 记录处理了多少文件
        $count = 0;
        // 如果目标目录不存在，则创建。
        if (! is_dir ( $target )) {
            mkdir ( $target, 0777, true );
            $count ++;
        }
        // 搜索目录下的所有文件
        foreach ( glob ( $source . '*' ) as $filename ) {
            if (is_dir ( $filename )) {
                // 如果是目录，递归合并子目录下的文件。
                $count += $this->copy_merge ( $filename, $target . basename ( $filename ) );
            } elseif (is_file ( $filename )) {
                // 如果是文件，判断当前文件与目标文件是否一样，不一样则拷贝覆盖。
                // 这里使用的是文件md5进行的一致性判断，可靠但性能低。
                if (! file_exists ( $target . basename ( $filename ) ) || md5 ( file_get_contents ( $filename ) ) != md5 ( file_get_contents ( $target . basename ( $filename ) ) )) {
                    copy ( $filename, $target . basename ( $filename ) );
                    $count ++;
                }
            }
        }

        // 返回处理了多少个文件
        return $count;
    }

    /**
     * 遍历删除文件
     * @param $dir 要删除的目录
     * @return bool 成功与否
     */
    public function deldir($dir) {
        if (trim($dir) == '') {
            return false;
        }
        //先删除目录下的文件：
        $dh=opendir($dir);
            while ($file=readdir($dh)) {
                if($file!="." && $file!="..") {
                  $fullpath=$dir."/".$file;
                  if(!is_dir($fullpath)) {
                      unlink($fullpath);
                  } else {
                      $this-> deldir($fullpath);
                  }
            }
        }

        closedir($dh);
        //删除当前文件夹：
        if(rmdir($dir)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * 遍历执行sql文件
     * @param $dir 要执行的目录
     * @return bool 成功与否
     */
    public function carry_sql($dir){
        if (trim($dir) == '') {
            return false;
        }
        $sql_file_res = $this->scan_dir($dir);
        if (!empty($sql_file_res)) {
            foreach ($sql_file_res as $k => $v) {
                if (!empty(strstr($v,'.sql'))) {
                    $sql_content = file_get_contents($dir.$v);
                    $sql_arr = explode(';', $sql_content);
                    //执行sql语句s
                    foreach ($sql_arr as $vv) {
                        if (!empty($vv)) {
                           try{
                               $sql_res = Db::execute($vv.';');
                           }catch (Exception $e) {
    
                            }
                           if (empty($sql_res)) {
                               // return false;
                           }
                        }
                    }
                }
            }
        } else {
            return false;
        }

        return true;

    }


    /**
     * 下载程序压缩包文件
     * @param $url 要下载的url
     * @param $save_dir 要存放的目录
     * @return res 成功返回下载信息 失败返回false
     */
    function down_file($url, $save_dir) {
        if (trim($url) == '') {
            return false;
        }
        if (trim($save_dir) == '') {
            return false;
        }
        if (0 !== strrpos($save_dir, '/')) {
            $save_dir.= '/';
        }
        $filename = basename($url);
        //创建保存目录
        if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
            return false;
        }
        //开始下载
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // stop verifying certificate
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $content = curl_exec($ch);
        $status = curl_getinfo($ch);
        curl_close($ch);

        // 判断执行结果
        if ($status['http_code'] ==200) {
            $size = strlen($content);
            //文件大小
            $fp2 = @fopen($save_dir . $filename , 'a');
            fwrite($fp2, $content);
            fclose($fp2);
            unset($content, $url);
            $res = [
                'status' =>$status['http_code'] ,
                'file_name' => $filename,
                'save_path' => $save_dir . $filename
            ];
        } else {
            $res = false;
        }

        return $res;
    }

}
