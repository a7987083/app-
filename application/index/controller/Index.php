<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Db;
class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';
    
    public function dylib(){
        header('Content-Type: application/json;charset=utf-8');
        $arr = ['code'=>0, 'msg'=>'未获取设备UDID！', 'data'=>[]];
        if(empty($_REQUEST['udid'])){
            echo json_encode($arr,JSON_UNESCAPED_UNICODE);die;
        }
        $config = Db::table('fa_config')->select();
        foreach ($config as $key=>$val)
        {
            if($val['name'] == 'name') $arr['data']['name'] = $val['value'];
            if($val['name'] == 'payURL') $arr['data']['pay'] = $val['value'];
            if($val['name'] == 'dylib-look') $arr['data']['look'] = $val['value'];
            if($val['name'] == 'dylib-control') $arr['data']['control'] = $val['value'];
            if($val['name'] == 'dylib-notice') $arr['data']['notice'] = $val['value'];
            if($val['name'] == 'dylib-time') $arr['data']['time'] = $val['value'];
            if($val['name'] == 'dylib-on') $arr['data']['on'] = $val['value'];
        }
        $udid =  $_REQUEST['udid'];
        $res = DB::name('kami')->where('udid',$udid)->find();
        $black = DB::name('black')->where('udid',$res['udid'])->find();
        if(!$res){
            $arr['msg'] = '未查到解锁记录！';
            $arr['code'] = 2;
            echo json_encode($arr,JSON_UNESCAPED_UNICODE);die;
        }
        if($res['endtime'] > time()){
            if ($black) {
                $arr['msg'] = 'UDID黑名单';
                $arr['code'] = 666;
                echo json_encode($arr,JSON_UNESCAPED_UNICODE);die;
            }
            $arr['msg'] = '验证成功';
            $arr['code'] = 1;
            echo json_encode($arr,JSON_UNESCAPED_UNICODE);die;
        }else{
            $res = DB::name('kami')->where('udid',$udid)->where(['endtime'=>['>',time()]])->find();
            if(!$res){
                $arr['msg'] = '设备解锁已到期！';
                $arr['code'] = 3;
                echo json_encode($arr,JSON_UNESCAPED_UNICODE);die;
            }else{
                if ($black) {
                    $arr['msg'] = 'UDID黑名单';
                    $arr['code'] = 666;
                    echo json_encode($arr,JSON_UNESCAPED_UNICODE);die;
                }
                $arr['msg'] = '验证成功';
                $arr['code'] = 1;
                echo json_encode($arr,JSON_UNESCAPED_UNICODE);die;
            }
        }
    }
    public function apiface(){
        if(empty($_REQUEST['udid'])){
            echo json_encode(['msg'=>'未获取设备udid'],JSON_UNESCAPED_UNICODE);die;
        }
        $udid =  $_REQUEST['udid'];
        $res = DB::name('kami')->where('udid',$udid)->find();
        if(!$res){
            echo json_encode(['msg'=>'未查到解锁记录'],JSON_UNESCAPED_UNICODE);die;
        }
        $endtime = $res['endtime'];
        if($endtime > time()){
            echo json_encode(['msg'=>'ok'],JSON_UNESCAPED_UNICODE);die;
        }else{
            $time = time();
            $res = DB::name('kami')->where('udid',$udid)->where(['endtime'=>['>',$time]])->find();
            if(!$res){
                echo json_encode(['msg'=>'解锁已到期'],JSON_UNESCAPED_UNICODE);die;
            }else{
                echo json_encode(['msg'=>'ok'],JSON_UNESCAPED_UNICODE);die;
            }
            
        }
    }
    public function index()
    {
    	if(!empty($_POST['uid'])){
    		$id = (int)$_POST['uid'];
    		$time = (int)date('d',time());
    		$s = DB::name('category')->where('id',$id)->where('cstime',$time)->find();
    		if($s){
    			DB::name('category')->where('id',$id)->setInc('cs');
    		}else{
    			DB::name('category')->where('id',$id)->update([
    				'cs' => 1,
    				'cstime' => $time
    			]);
    		}
    		return 'ok';
    	}
    	$data['img'] = DB::name('attachment')->whereNotNull('urls')->select();
    	$data['category'] = DB::name('category')->where('pid',0)->where('status','normal')->order('weigh desc')->select();
    	$data['xm'] = [];
    	foreach ($data['category'] as $v){
    		$data['xm'][$v['id']] = DB::name('category')->where('pid',$v['id'])->where('status','normal')->order('weigh desc')->select();
    	}
        return $this->view->fetch('',$data);
    }

}
