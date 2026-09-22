<?php

namespace app\admin\command;

use app\common\library\Ipa\IpaCompareService;
use app\common\library\Ipa\IpaOpsSettings;
use app\common\library\Ipa\IpaParserService;
use app\common\library\Ipa\SecretBox;
use app\common\library\Ipa\WorkerState;
use think\Db;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class IpaParseWorker extends Command
{
    protected function configure()
    {
        $this->setName('ipa:parse-worker')
            ->addOption('once', null, Option::VALUE_NONE, 'Parse at most one IPA and exit')
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Idle sleep seconds', 2)
            ->setDescription('Parse discovered IPA assets with HTTP Range and configured rate limits');
    }

    protected function execute(Input $input, Output $output)
    {
        $once=(bool)$input->getOption('once');
        $sleep=max(1,min(30,(int)$input->getOption('sleep')));
        $workerId=gethostname().':'.getmypid();
        $output->info('IPA parse worker started: '.$workerId);

        try {
            $encrypted=Db::name('ipa_source')->where('enabled',1)->where('token_ciphertext','<>','')->find();
            if ($encrypted) SecretBox::decrypt((string)$encrypted['token_ciphertext']);
        } catch (\Exception $e) {
            $output->error('Parser preflight failed: '.$e->getMessage());
            return 2;
        }

        WorkerState::heartbeat('parse',$workerId,'idle',0);
        do {
            WorkerState::heartbeat('parse',$workerId,'idle',0);
            try { $quota=IpaOpsSettings::parseQuotaStatus(); }
            catch (\Exception $e) { $quota=['allowed'=>true,'settings'=>IpaOpsSettings::all()]; }
            if (empty($quota['allowed'])) {
                WorkerState::heartbeat('parse',$workerId,'throttled',0);
                if ($once) return 0;
                sleep(max(5,$sleep));
                continue;
            }

            $asset=$this->claimAsset($workerId);
            if (!$asset) {
                if ($once) { WorkerState::heartbeat('parse',$workerId,'stopped',0); return 0; }
                sleep($sleep); continue;
            }

            WorkerState::heartbeat('parse',$workerId,'working',(int)$asset['id']);
            $source=Db::name('ipa_source')->where('id',(int)$asset['source_id'])->find();
            if (!$source || !(int)$source['enabled']) {
                $e=new \RuntimeException('OpenList 数据源不存在或已停用');
                $this->failAsset($asset['id'],$e);
                IpaOpsSettings::recordAttempt($asset['id'],$workerId,'failed',$e->getMessage());
                if ($once) break;
                continue;
            }

            try {
                $token=$this->decryptToken($source);
                $meta=IpaParserService::parseAsset((int)$asset['id'],$source,$token);
                IpaOpsSettings::recordAttempt($asset['id'],$workerId,'success','');
                try { IpaCompareService::refreshAsset((int)$asset['id']); } catch (\Exception $compareError) {
                    $output->error(sprintf('compare warning asset=%d: %s',$asset['id'],$compareError->getMessage()));
                }
                $output->info(sprintf('parsed asset=%d bundle=%s version=%s',$asset['id'],$meta['bundle_id'],$meta['app_version']));
            } catch (\Exception $e) {
                $this->failAsset($asset['id'],$e);
                IpaOpsSettings::recordAttempt($asset['id'],$workerId,'failed',$e->getMessage());
                $output->error(sprintf('parse failed asset=%d: %s',$asset['id'],$e->getMessage()));
            }
            WorkerState::heartbeat('parse',$workerId,'idle',0);
            if ($once) break;
        } while (true);

        WorkerState::heartbeat('parse',$workerId,'stopped',0);
        return 0;
    }

    protected function claimAsset($workerId)
    {
        $now=time();
        Db::startTrans();
        try {
            Db::name('ipa_asset')->where('status','parsing')->where('updated_at','<',$now-600)->update(['status'=>'discovered','updated_at'=>$now]);
            $asset=Db::name('ipa_asset')->where('status','discovered')->order('id asc')->lock(true)->find();
            if (!$asset) { Db::commit(); return null; }
            Db::name('ipa_asset')->where('id',(int)$asset['id'])->update(['status'=>'parsing','last_error'=>null,'updated_at'=>$now]);
            Db::commit(); return $asset;
        } catch (\Exception $e) { Db::rollback(); throw $e; }
    }

    protected function decryptToken(array $source)
    {
        if (empty($source['token_ciphertext'])) return '';
        return SecretBox::decrypt((string)$source['token_ciphertext']);
    }

    protected function failAsset($assetId,\Exception $e)
    {
        IpaParserService::markParseError((int)$assetId,$e);
    }
}
