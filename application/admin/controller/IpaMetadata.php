<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaMetadataQueryService;
use app\common\library\IpaParserService;

class IpaMetadata extends Backend
{
    protected $layout='default';

    public function sourceList()
    {
        try{
            $data=IpaMetadataQueryService::listing(
                $this->request->get('state',''),
                $this->request->get('q',''),
                (int)$this->request->get('page/d',1),
                (int)$this->request->get('limit/d',50),
                $this->request->get('sort','id'),
                $this->request->get('order','desc'),
                true
            );
        }catch(\Exception $e){$this->error($e->getMessage());return;}
        $this->success('',null,$data);
    }

    public function parseOne()
    {
        if(!$this->request->isPost()){$this->error('Method not allowed');return;}
        try{$result=IpaParserService::parseBatch(0,1);}
        catch(\Exception $e){$this->error($e->getMessage());return;}
        $selected=isset($result['selected'])?(int)$result['selected']:0;
        $this->success($selected?'后台已消费 1 个 IPA':'当前没有可解析的 IPA',null,$result);
    }

    public function source_list(){return $this->sourceList();}
    public function parse_one(){return $this->parseOne();}
}
