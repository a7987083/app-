<?php

namespace app\admin\command;

use app\common\library\Ipa\IpaParserService;
use app\common\library\Ipa\SecretBox;
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
            ->setDescription('Parse discovered IPA assets with HTTP Range');
    }

    protected function execute(Input $input, Output $output)
    {
        $once = (bool)$input->getOption('once');
        $sleep = max(1, min(30, (int)$input->getOption('sleep')));
        $workerId = gethostname() . ':' . getmypid();
        $output->info('IPA parse worker started: ' . $workerId);

        do {
            $asset = $this->claimAsset($workerId);
            if (!$asset) {
                if ($once) return 0;
                sleep($sleep);
                continue;
            }

            $source = Db::name('ipa_source')->where('id', (int)$asset['source_id'])->find();
            if (!$source || !(int)$source['enabled']) {
                $this->failAsset($asset['id'], new \RuntimeException('Source unavailable'));
                if ($once) break;
                continue;
            }

            try {
                $token = $this->decryptToken($source);
                $meta = IpaParserService::parseAsset((int)$asset['id'], $source, $token);
                $output->info(sprintf('parsed asset=%d bundle=%s version=%s', $asset['id'], $meta['bundle_id'], $meta['app_version']));
            } catch (\Exception $e) {
                $this->failAsset($asset['id'], $e);
                $output->error(sprintf('parse failed asset=%d: %s', $asset['id'], $e->getMessage()));
            }

            if ($once) break;
        } while (true);
        return 0;
    }

    protected function claimAsset($workerId)
    {
        $now = time();
        Db::startTrans();
        try {
            Db::name('ipa_asset')
                ->where('status', 'parsing')
                ->where('updated_at', '<', $now - 600)
                ->update(['status' => 'discovered', 'updated_at' => $now]);

            $asset = Db::name('ipa_asset')
                ->where('status', 'discovered')
                ->order('id asc')
                ->lock(true)
                ->find();
            if (!$asset) {
                Db::commit();
                return null;
            }
            Db::name('ipa_asset')->where('id', (int)$asset['id'])->update([
                'status' => 'parsing',
                'last_error' => null,
                'updated_at' => $now,
            ]);
            Db::commit();
            return $asset;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected function decryptToken(array $source)
    {
        if (empty($source['token_ciphertext'])) return '';
        return SecretBox::decrypt((string)$source['token_ciphertext']);
    }

    protected function failAsset($assetId, \Exception $e)
    {
        IpaParserService::markParseError((int)$assetId, $e);
    }
}
