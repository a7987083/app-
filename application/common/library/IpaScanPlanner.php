<?php

namespace app\common\library;

class IpaScanPlanner
{
    public static function plan(array $remoteRows, array $localRows)
    {
        $remoteByHash = [];
        foreach ($remoteRows as $row) {
            if (empty($row['remote_path_hash']) && !empty($row['remote_path'])) $row['remote_path_hash'] = IpaRemoteFile::pathHash($row['remote_path']);
            if (!empty($row['remote_path_hash'])) $remoteByHash[$row['remote_path_hash']] = $row;
        }
        $localByHash = [];
        foreach ($localRows as $row) {
            if (empty($row['remote_path_hash']) && !empty($row['remote_path'])) $row['remote_path_hash'] = IpaRemoteFile::pathHash($row['remote_path']);
            if (!empty($row['remote_path_hash'])) $localByHash[$row['remote_path_hash']] = $row;
        }
        $result = ['new'=>[],'changed'=>[],'unchanged'=>[],'missing'=>[],'summary'=>['remote_total'=>count($remoteByHash),'local_total'=>count($localByHash),'new'=>0,'changed'=>0,'unchanged'=>0,'missing'=>0]];
        foreach ($remoteByHash as $hash => $remote) {
            if (!isset($localByHash[$hash])) {
                $result['new'][] = ['remote'=>$remote,'local'=>null,'reason'=>'remote_path_new'];
                continue;
            }
            $local = $localByHash[$hash];
            $remoteFp = IpaRemoteFile::fingerprint($remote);
            $localFp = IpaRemoteFile::fingerprint($local);
            if ($remoteFp === $localFp) {
                $result['unchanged'][] = ['remote'=>$remote,'local'=>$local,'reason'=>'fingerprint_same'];
            } else {
                $result['changed'][] = ['remote'=>$remote,'local'=>$local,'reason'=>'fingerprint_changed','old_fingerprint'=>$localFp,'new_fingerprint'=>$remoteFp];
            }
        }
        foreach ($localByHash as $hash => $local) {
            if (!isset($remoteByHash[$hash])) $result['missing'][] = ['remote'=>null,'local'=>$local,'reason'=>'remote_path_missing'];
        }
        foreach (['new','changed','unchanged','missing'] as $key) $result['summary'][$key] = count($result[$key]);
        return $result;
    }
}
