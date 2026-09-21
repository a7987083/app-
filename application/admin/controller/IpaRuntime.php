<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaWorkerService;
use app\common\library\IpaParserRunner;

class IpaRuntime extends Backend
{
    protected $noNeedRight = ['health'];
    protected $layout = 'default';

    public function health()
    {
        if (!$this->request->isAjax()) $this->error('Method not allowed');
        $worker = IpaWorkerService::workerStatus();
        $parser = IpaParserRunner::health(0.5);
        $ok = !empty($worker['online']) && !empty($parser['ok']);
        $this->success('', null, [
            'ok' => $ok,
            'worker' => $worker,
            'parser' => $parser,
            'bootstrap_command' => 'sudo bash scripts/zonoe-server-bootstrap.sh',
            'worker_install_command' => 'sudo bash scripts/install-ipa-worker-service.sh',
            'parser_install_command' => 'sudo bash scripts/install-ipa-parser-service.sh',
        ]);
    }
}
