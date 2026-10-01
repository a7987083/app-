<?php

namespace app\common\library\Ipa;

use think\Db;

/** Server-only RSA-2048 signing key for runtime config and session tokens. */
class DylibSigningKey
{
    const ALGORITHM = 'rsa-2048-sha256';

    public static function ensure()
    {
        $row=Db::name('dylib_runtime_config')->where('id',1)->find();
        if(!$row)throw new \RuntimeException('Dylib runtime configuration is missing');
        $ciphertext=isset($row['signing_private_key_ciphertext'])?trim((string)$row['signing_private_key_ciphertext']):'';
        $publicKey=isset($row['signing_public_key_pem'])?trim((string)$row['signing_public_key_pem']):'';
        $keyId=isset($row['signing_key_id'])?trim((string)$row['signing_key_id']):'';
        if($ciphertext!==''&&$publicKey!==''&&$keyId!=='')return ['private_key'=>SecretBox::decrypt($ciphertext),'public_key'=>$publicKey."\n",'key_id'=>$keyId,'algorithm'=>self::ALGORITHM];

        $resource=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>2048]);
        if($resource===false)throw new \RuntimeException('Unable to generate Dylib signing key');
        $privateKey='';if(!openssl_pkey_export($resource,$privateKey))throw new \RuntimeException('Unable to export Dylib signing key');
        $details=openssl_pkey_get_details($resource);if(!is_array($details)||empty($details['key']))throw new \RuntimeException('Unable to export Dylib public key');
        $publicKey=trim((string)$details['key'])."\n";$keyId=substr(hash('sha256',$publicKey),0,32);
        Db::name('dylib_runtime_config')->where('id',1)->update(['signing_private_key_ciphertext'=>SecretBox::encrypt($privateKey),'signing_public_key_pem'=>$publicKey,'signing_key_id'=>$keyId,'updated_at'=>time()]);
        return ['private_key'=>$privateKey,'public_key'=>$publicKey,'key_id'=>$keyId,'algorithm'=>self::ALGORITHM];
    }

    public static function publicKeyPem(){return self::ensure()['public_key'];}
    public static function keyId(){return self::ensure()['key_id'];}
    public static function algorithm(){return self::ALGORITHM;}
    public static function sign($data)
    {
        $key=self::ensure();$signature='';
        if(!openssl_sign((string)$data,$signature,$key['private_key'],OPENSSL_ALGO_SHA256))throw new \RuntimeException('Unable to sign Dylib data');
        return base64_encode($signature);
    }
}
