<?php

$root = dirname(__DIR__);
$output = isset($argv[1]) && $argv[1] !== '' ? $argv[1] : $root . '/zonoe-online-update.zip';
$manifest = $root . '/release/online-update-files.txt';
$sqlDir = $root . '/release/sql';
$phase20Sql = [
    '2026091901_phase20_ipa_foundation.sql' => $root . '/application/admin/command/Install/phase20_ipa_foundation.sql',
    '2026091902_phase20_ipa_center.sql' => $root . '/application/admin/command/Install/phase20_ipa_center.sql',
    '2026091903_phase20_ipa_scan.sql' => $root . '/application/admin/command/Install/phase20_ipa_scan.sql',
    '2026091904_phase20_ipa_parser.sql' => $root . '/application/admin/command/Install/phase20_ipa_parser.sql',
    '2026091905_phase20_ipa_binding.sql' => $root . '/application/admin/command/Install/phase20_ipa_binding.sql',
    '2026091906_phase20_ipa_writeback.sql' => $root . '/application/admin/command/Install/phase20_ipa_writeback.sql',
    '2026091907_phase20_ipa_governance.sql' => $root . '/application/admin/command/Install/phase20_ipa_governance.sql',
    '2026091908_phase20_ipa_production.sql' => $root . '/application/admin/command/Install/phase20_ipa_production.sql',
    '2026091909_phase20_ipa_lifecycle.sql' => $root . '/application/admin/command/Install/phase20_ipa_lifecycle.sql',
    '2026091910_phase20_ipa_sources_v2.sql' => $root . '/application/admin/command/Install/phase20_ipa_sources_v2.sql',
    '2026091911_phase20_ipa_workset.sql' => $root . '/application/admin/command/Install/phase20_ipa_workset.sql',
    '2026091915_phase20_ipa_worker.sql' => $root . '/application/admin/command/Install/phase20_ipa_worker.sql',
];

function release_fail($message){fwrite(STDERR,"FAIL build_online_update: {$message}\n");exit(1);}
function release_safe_path($relative){$relative=str_replace('\\','/',trim($relative));if($relative===''||$relative[0]==='/'||preg_match('/^[A-Za-z]:\//',$relative))return false;foreach(explode('/',$relative) as $part){if($part==='..'||$part==='')return false;}$protected=['application/database.php','.env','uploads','public/uploads','runtime'];foreach($protected as $path){if($relative===$path||strpos($relative,$path.'/')===0)return false;}return true;}
if(!is_file($manifest))release_fail('release/online-update-files.txt missing');
if(!class_exists('ZipArchive'))release_fail('ZipArchive extension missing');
$lines=file($manifest,FILE_IGNORE_NEW_LINES);if(!is_array($lines))release_fail('cannot read update manifest');
$files=[];foreach($lines as $line){$line=trim($line);if($line===''||$line[0]==='#')continue;if(!release_safe_path($line))release_fail('unsafe/protected manifest path: '.$line);$source=$root.'/'.str_replace('/',DIRECTORY_SEPARATOR,$line);if(!is_file($source))release_fail('manifest file missing: '.$line);$files[]=$line;}
if(!$files)release_fail('update manifest is empty');
foreach($phase20Sql as $targetName=>$sourcePath){if(!preg_match('/^[A-Za-z0-9._-]+\.sql$/',$targetName))release_fail('unsafe Phase 20 SQL filename: '.$targetName);if(!is_file($sourcePath))release_fail('Phase 20 SQL source missing: '.str_replace($root.'/','',$sourcePath));}
@unlink($output);$zip=new ZipArchive();if($zip->open($output,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)release_fail('cannot create zip: '.$output);
$zip->addEmptyDir('program');foreach($files as $relative){$source=$root.'/'.str_replace('/',DIRECTORY_SEPARATOR,$relative);if(!$zip->addFile($source,'program/'.$relative)){$zip->close();release_fail('cannot add file: '.$relative);}}
$sqlCount=0;$mysqlAdded=false;if(is_dir($sqlDir)){$sqlFiles=glob($sqlDir.'/*.sql');if(is_array($sqlFiles)&&$sqlFiles){sort($sqlFiles,SORT_STRING);$zip->addEmptyDir('mysql');$mysqlAdded=true;foreach($sqlFiles as $sql){$name=basename($sql);if(!preg_match('/^[A-Za-z0-9._-]+\.sql$/',$name)){$zip->close();release_fail('unsafe SQL filename: '.$name);}if(!$zip->addFile($sql,'mysql/'.$name)){$zip->close();release_fail('cannot add SQL: '.$name);}$sqlCount++;}}}
if(!$mysqlAdded)$zip->addEmptyDir('mysql');foreach($phase20Sql as $targetName=>$sourcePath){if(!$zip->addFile($sourcePath,'mysql/'.$targetName)){$zip->close();release_fail('cannot add Phase 20 SQL: '.$targetName);}$sqlCount++;}
$zip->close();if(!is_file($output)||filesize($output)<=0)release_fail('zip output missing');$sha=hash_file('sha256',$output);$shaFile=$output.'.sha256';if(file_put_contents($shaFile,$sha.'  '.basename($output)."\n")===false)release_fail('cannot write sha256 file');
fwrite(STDOUT,'OK build_online_update files='.count($files).' sql='.$sqlCount.' phase20_sql='.count($phase20Sql).' sha256='.$sha."\n");
