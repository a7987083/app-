<?php
function p20WorkerAssert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase20_worker_contract_test: {$message}\n");exit(1);}}
$root=dirname(__DIR__);
$worker=file_get_contents($root.'/application/common/library/IpaWorkerService.php');
$scan=file_get_contents($root.'/application/common/library/IpaScanService.php');
$parser=file_get_contents($root.'/application/common/library/IpaParserService.php');
$command=file_get_contents($root.'/application/admin/command/IpaWorker.php');
$registry=file_get_contents($root.'/application/command.php');
$sql=file_get_contents($root.'/application/admin/command/Install/phase20_ipa_worker.sql');
$manifest=file_get_contents($root.'/release/online-update-files.txt');
$installer=file_get_contents($root.'/scripts/install-ipa-worker-service.sh');
$bootstrap=file_get_contents($root.'/scripts/zonoe-server-bootstrap.sh');
$runtime=file_get_contents($root.'/application/admin/controller/IpaRuntime.php');
$metadataView=file_get_contents($root.'/application/admin/view/ipa_center/metadata.html');

p20WorkerAssert(strpos($worker,"Db::name('ipa_worker_job')")!==false,'worker uses durable MySQL job queue');
p20WorkerAssert(strpos($worker,"where('state','in',['queued','interrupted'])")!==false,'worker claims only recoverable queued jobs');
p20WorkerAssert(strpos($worker,"'state'=>'running'")!==false,'worker atomically transitions jobs to running');
p20WorkerAssert(strpos($worker,'enqueueScan')!==false && strpos($worker,'enqueueParse')!==false,'worker exposes scan and parse enqueue APIs');
p20WorkerAssert(strpos($worker,'IpaScanService::runTask')!==false,'worker executes scan jobs through scan service');
p20WorkerAssert(strpos($worker,'IpaParserService::parseBatch')!==false,'worker executes parse requests through one-item parser service');
p20WorkerAssert(strpos($worker,'workerStatus')!==false && strpos($worker,'heartbeat_at')!==false,'worker exposes heartbeat health');
p20WorkerAssert(strpos($worker,'register_shutdown_function')===false,'web enqueue must never run jobs in PHP-FPM shutdown');
p20WorkerAssert(strpos($worker,'fastcgi_finish_request')===false,'web enqueue must not retain an FPM request while consuming jobs');
p20WorkerAssert(strpos($worker,"return !empty(\$status['online'])?'persistent':'offline';")!==false,'dispatch compatibility API reports persistent/offline only');
p20WorkerAssert(strpos($scan,'IpaWorkerService::enqueueScan')!==false,'scan web compatibility hook enqueues instead of forking');
p20WorkerAssert(strpos($scan,'nohup ')===false && strpos($scan,"function_exists('exec')")===false,'scan service no longer forks transient CLI workers');
p20WorkerAssert(strpos($parser,'IpaWorkerService::enqueueParse')!==false,'parser web compatibility hook enqueues instead of forking');
p20WorkerAssert(strpos($parser,'nohup ')===false && strpos($parser,"function_exists('exec')")===false,'parser service no longer forks transient CLI workers');
p20WorkerAssert(strpos($command,"setName('ipa:worker')")!==false,'persistent worker CLI command exists');
p20WorkerAssert(strpos($command,"addOption('once'")!==false,'worker has deterministic one-job mode for CI and diagnostics');
p20WorkerAssert(strpos($registry,'app\\admin\\command\\IpaWorker')!==false,'worker command is registered');
p20WorkerAssert(strpos($sql,'fa_ipa_worker_job')!==false && strpos($sql,'fa_ipa_worker_state')!==false,'worker migration creates queue and heartbeat tables');
p20WorkerAssert(strpos($sql,'uniq_job_key')!==false && strpos($sql,'idx_state_id')!==false,'worker migration has queue integrity indexes');
p20WorkerAssert(strpos($installer,'ipa:worker --sleep=2')!==false && strpos($installer,'Restart=always')!==false,'systemd installer owns persistent worker lifecycle');
p20WorkerAssert(strpos($bootstrap,'install-ipa-parser-service.sh')!==false && strpos($bootstrap,'install-ipa-worker-service.sh')!==false,'server bootstrap restores parser and worker together');
p20WorkerAssert(strpos($runtime,'IpaWorkerService::workerStatus')!==false && strpos($runtime,'IpaParserRunner::health')!==false,'admin runtime health checks both services');
p20WorkerAssert(strpos($metadataView,'ipa_runtime/health')!==false && strpos($metadataView,'zonoe-server-bootstrap.sh')!==false,'metadata UI exposes runtime failure recovery');
p20WorkerAssert(strpos($manifest,'application/common/library/IpaWorkerService.php')!==false,'online update contains worker service');
p20WorkerAssert(strpos($manifest,'application/admin/command/IpaWorker.php')!==false,'online update contains worker command');
p20WorkerAssert(strpos($manifest,'application/admin/controller/IpaRuntime.php')!==false,'online update contains runtime health controller');
p20WorkerAssert(strpos($manifest,'scripts/install-ipa-worker-service.sh')!==false && strpos($manifest,'scripts/zonoe-server-bootstrap.sh')!==false,'online update contains worker/bootstrap installers');
p20WorkerAssert(strpos($manifest,'phase20_ipa_worker.sql')!==false,'online update contains worker migration');

echo "OK phase20_worker_contract_test web_queue_only=passed runtime_health=passed\n";
