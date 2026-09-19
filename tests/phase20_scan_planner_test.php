<?php
require_once dirname(__DIR__) . '/application/common/library/IpaRemoteFile.php';
require_once dirname(__DIR__) . '/application/common/library/IpaScanPlanner.php';

use app\common\library\IpaRemoteFile;
use app\common\library\IpaScanPlanner;

function p20ScanAssert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase20_scan_planner_test: {$message}\n");exit(1);}}

p20ScanAssert(IpaRemoteFile::normalizePath('a//app/')==='/a/app','path normalization');
p20ScanAssert(IpaRemoteFile::isIpaName('Demo.IPA')===true,'IPA extension is case insensitive');
$entry=['name'=>'Demo.ipa','size'=>1234,'modified'=>'2026-09-19T01:02:03+00:00','hash_info'=>['md5'=>'0123456789abcdef0123456789abcdef']];
$remote=IpaRemoteFile::fromOpenListEntry('openlist','/a/app',$entry,'https://cdn.example/d/{path}');
p20ScanAssert($remote['remote_path']==='/a/app/Demo.ipa','remote path');
p20ScanAssert($remote['public_url']==='https://cdn.example/d/a/app/Demo.ipa','public URL template');
p20ScanAssert(IpaRemoteFile::fingerprint($remote)==='md5:0123456789abcdef0123456789abcdef','MD5 precedence');
$jsonHash=['name'=>'HashInfo.ipa','size'=>1234,'modified'=>'2026-09-19T01:02:03+00:00','hashinfo'=>'{"md5":"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"}'];
p20ScanAssert(IpaRemoteFile::extractMd5($jsonHash)==='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','OpenList hashinfo JSON MD5');

$remote2=$remote;$remote2['remote_path']='/a/app/New.ipa';$remote2['remote_path_hash']=IpaRemoteFile::pathHash($remote2['remote_path']);$remote2['md5']='';$remote2['etag']='v2';
$localSame=$remote;$localSame['id']=1;
$localMissing=$remote;$localMissing['id']=2;$localMissing['remote_path']='/a/app/Missing.ipa';$localMissing['remote_path_hash']=IpaRemoteFile::pathHash($localMissing['remote_path']);
$plan=IpaScanPlanner::plan([$remote,$remote2],[$localSame,$localMissing]);
p20ScanAssert($plan['summary']['new']===1,'one new');
p20ScanAssert($plan['summary']['unchanged']===1,'one unchanged');
p20ScanAssert($plan['summary']['missing']===1,'one missing');
p20ScanAssert($plan['summary']['changed']===0,'no changed');
$changed=$remote;$changed['md5']='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$plan2=IpaScanPlanner::plan([$changed],[$localSame]);
p20ScanAssert($plan2['summary']['changed']===1,'changed MD5 detected');

// Mature reference behavior: when MD5 is unavailable, ETag/sign rotation is
// not content change.  Fall back to size + modified only.
$statOld=$remote;$statOld['md5']='';$statOld['etag']='old';$statOld['file_size']=555;$statOld['remote_mtime']=100;
$statNew=$statOld;$statNew['etag']='new';
p20ScanAssert(IpaRemoteFile::fingerprint($statOld)===IpaRemoteFile::fingerprint($statNew),'ETag is not authoritative without MD5');
$statNew['remote_mtime']=101;
p20ScanAssert(IpaRemoteFile::fingerprint($statOld)!==IpaRemoteFile::fingerprint($statNew),'size+modified fallback detects change');
echo "OK phase20_scan_planner_test\n";
