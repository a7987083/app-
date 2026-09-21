# ZONDylibVerify Objective-C Client

This client is designed to be compiled into the injected Objective-C / Objective-C++ dylib.
It talks to `POST /index/dylib_verify/verify` and does not replace the legacy `Index::dylib()` or `Index::apiface()` endpoints.

## Required frameworks

- Foundation.framework
- Security.framework
- libcommonCrypto (CommonCrypto headers are provided by the iOS SDK)
- libdl (normally available through the system runtime)

## Configuration

```objc
ZONVerifyConfiguration *cfg = [ZONVerifyConfiguration new];
cfg.endpointURL = [NSURL URLWithString:@"https://example.com/index/dylib_verify/verify"];
cfg.dylibKey = @"zonoe.main";
cfg.dylibVersion = @"1.0.0";
cfg.dylibBuild = @"1";
cfg.verifySecret = @"<copy the secret generated in Dylib 验证中心>";
cfg.requestTimeout = 10.0;
cfg.udidProvider = ^NSString * _Nullable{
    return ExistingProjectUDID();
};

ZONVerifyClient *client = [[ZONVerifyClient alloc] initWithConfiguration:cfg];
[client verifyWithCompletion:^(ZONVerifyResult *result) {
    if (result.allowed) {
        // enable protected dylib functionality
        return;
    }
    if ([result.action isEqualToString:@"show_message"]) {
        // show result.message
    } else if ([result.action isEqualToString:@"block"]) {
        // block protected module according to product policy
    } else {
        // disable_feature
    }
}];
```

## UDID

The SDK intentionally does not derive a substitute device identifier. The product requirement is **UDID only**, so `udidProvider` must return the UDID already obtained by the existing signed/injected environment.

## Request signing

The server and client sign this exact UTF-8 string using HMAC-SHA256 and the per-dylib verify secret:

```text
udid\nbundle_id\ndylib_key\ndylib_version\ndylib_build\ndylib_sha256\ntimestamp\nnonce
```

The signature sent in JSON is lowercase 64-character hexadecimal.

The shared secret is an anti-tampering/request-authentication signal, not hardware-backed attestation: a sufficiently capable reverse engineer can recover secrets embedded in client binaries. Server-side UDID licensing, version state, BundleID binding, nonce replay defense, short-lived sessions and optional dylib SHA256 checks remain independent layers.

## Offline behavior

A successful verification response is cached in the iOS Keychain with `kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly`. When the network or server is unavailable, the SDK accepts the cached result only until `verified_at + offline_grace_seconds`. The default server policy is 900 seconds (15 minutes).

Explicit server rejection (expired license, blacklist, blocked/revoked version, BundleID mismatch, signature mismatch, integrity mismatch) clears the cached offline authorization instead of falling back to it.
