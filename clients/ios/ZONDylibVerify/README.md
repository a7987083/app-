# ZONDylibVerify Objective-C Client

Protocol v3 用于注入式 Objective-C / Objective-C++ Dylib 的运行时授权，最低 iOS 13。

## Security model

- 客户端首次运行生成 P-256 Device KeyPair。
- 私钥持久化到 Keychain，不上传服务器。
- 服务端只保存设备公钥。
- 每次在线验证先申请一次性 Challenge，再由 Device Private Key 做 ECDSA P-256 SHA-256 签名。
- 首次设备公钥绑定时，由宿主项目提供当前有效卡密；绑定完成后后续验证不再发送卡密。
- Runtime Config 由服务端私钥签名，生成代码只内置服务端公钥。
- 在线验证成功后返回短期 Session Token、permissions、notice、app_update 等现有业务结果。

## Required frameworks

- Foundation.framework
- Security.framework
- CommonCrypto
- libdl / dyld Mach-O runtime

## Configure

推荐直接使用“Dylib 验证中心 → OC 接入代码”生成的配置类：

```objc
ZONDylibVerifyConfiguration *cfg =
    [ZONDylibConfig configurationWithUDIDProvider:^NSString *{
        return ExistingProjectUDID();
    } licenseCodeProvider:^NSString *{
        return ExistingProjectLicenseCode();
    }];

ZONDylibVerify *client = [[ZONDylibVerify alloc] initWithConfiguration:cfg];
[client verifyWithCompletion:^(ZONDylibVerifyResult *result) {
    if (result.isAllowed) {
        // 使用 result.permissions / result.accessLevel
    } else {
        // 按 result.code + result.action 处理
    }
}];
```

`licenseCodeProvider` 只在服务端返回 `enrollment_required=true` 时使用。

## Protocol v3 flow

```text
Client loads/generates P-256 Device KeyPair
  ↓
GET /index/dylib_verify/config?dylib_key=...
  ↓
verify signed Runtime Config with embedded Server Public Key
  ↓
POST /index/dylib_verify/challenge
  udid + dylib_key + device_public_key
  ↓
challenge_id + challenge + expires_at
  ↓
sign canonical body with Device Private Key
  ↓
POST /index/dylib_verify/verify
  App/Dylib identity + challenge + public key + device signature
  first enrollment only: license_code
  ↓
Server verifies device signature
  ↓
blacklist / license / App identity / Dylib version / SHA256 / permission checks
  ↓
short-lived token + permissions + notice + app_update
```

## Canonical device proof

Fields are UTF-8 text joined with real newlines in this exact order:

```text
zonoe-dylib-auth-v3
challenge_id
challenge
udid
bundle_id
dylib_key
dylib_version
dylib_build
dylib_sha256
app_executable
app_macho_uuid
app_version
app_build
```

The client signs this text with `kSecKeyAlgorithmECDSASignatureMessageX962SHA256` and sends the DER signature as Base64.

## Device re-enrollment

If Keychain/private-key state changes, the server returns `device_key_mismatch`. An administrator revokes the existing device key in “Dylib 验证中心 → 设备密钥”; the client can then bind a new key using the current valid card.
