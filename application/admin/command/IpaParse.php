<?php
namespace app\admin\command;
use app\common\library\IpaParserService;
use think\console\Command;use think\console\Input;use think\console\input\Option;use think\console\Output;
class IpaParse extends Command{protected function configure(){$this->setName('ipa:parse')->addOption('task',null,Option::VALUE_OPTIONAL,'Only parse one scan task',0)->addOption('limit',null,Option::VALUE_OPTIONAL,'Maximum parser items',0)->setDescription('Phase 20 HTTP Range IPA metadata parser');}protected function execute(Input $input,Output $output){$summary=IpaParserService::parseBatch((int)$input->getOption('task'),(int)$input->getOption('limit'));$output->info(json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}}
