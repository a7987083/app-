# ZONDylibVerify Objective-C Client

`ZONDylibVerify` 用于注入式 Objective-C / Objective-C++ Dylib 的运行时授权。2430 继续保留 2428 已有的权限模型、App 身份识别、游戏更新提示、远程通知、离线 grace、Last-Known-Good Runtime Config 和可迁移服务器发现，只替换客户端认证层。

它不会替换旧的 `Index::dylib()` / `Index::apiface()`；这些 2428 API 继续保留。

## 1. Required frameworks

- Foundation.framework
- Security.framework
- CommonCrypto
- libdl / dyld Mach-O runtime

## 2. Configure once

```objc
ZONVerifyConfiguration *cfg = [ZONVerifyConfiguration new];

cfg.bootstrapURLs = @[
    [NSURL URLWithString:@"https://bootstrap-a.example.com/index/dylib_verify/config"],
    [NSURL URLWithString:@"https://bootstrap-b.example.net/index/dylib_verify/config"]
];

// Bootstrap/LKG 都失败时继续使用原有 endpointURL 兜底。
cfg.endpointURL = [NSURL URLWithString:@"https://api.example.com/index/dylib_verify/verify"];

cfg.dylibKey = @"zonoe.main";
cfg.dylibVersion = @"1.0.0";
cfg.dylibBuild = @"1";

// 由 Dylib Center Codegen 自动写入。这里永远只是服务器公钥。
cfg.serverPublicKeyPEM = @"-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----\n";
cfg.serverKeyID = @"<server-key-id>";

cfg.requestTimeout = 10.0;
cfg.udidProvider = ^NSString * _Nullable{
    return ExistingProjectUDID();
};

// 仅设备公钥第一次登记时需要。返回当前 UDID 已激活的卡密。
cfg.licenseCodeProvider = ^NSString * _Nullable{
    return ExistingActivatedCardCode();
};
```

`udidProvider` 必须继续返回项目现有的真实 UDID。SDK 不生成替代设备标识。

## 3. Verify and render permissions

```objc
ZONVerifyClient *client = [[ZONVerifyClient alloc] initWithConfiguration:cfg];
[client verifyWithCompletion:^(ZONVerifyResult *result) {
    if (!result.allowed) {
        // 按 result.code / result.action / result.message 处理失败。
        return;
    }

    if ([result.permissions[@"normal_menu"] boolValue]) {
        // 显示普通菜单
    }
    if ([result.permissions[@"extra_menu"] boolValue]) {
        // 显示额外菜单
    }
    if ([result.permissions[@"extra_features"] boolValue]) {
        // 启用额外功能
    }

    if ([result.appUpdate[@"available"] boolValue]) {
        // title/message/button/download_url 仍由服务器下发。
    }

    if (result.notice) {
        // 可按 notice_key + revision 做本地展示策略。
    }
}];
```

## 4. Three card scopes

服务端继续负责计算，客户端不自行推断卡密类型：

| card_scope | 条件 | access_level | 权限 |
| --- | --- | --- | --- |
| `2` 仅验证 | 任意 App | `basic` | 普通菜单 |
| `3` 指定 App | 当前 App 身份命中 `fa_kami_app.app_id` | `app_plus` | 普通 + 额外菜单/功能 |
| `1` 全软件源 | 任意 App | `global_plus` | 普通 + 额外菜单/功能 |

同一 UDID 有多张有效卡时，当前 App 下仍取最高适用权限：`global_plus > app_plus > basic > block`。

## 5. App identity

Protocol v3 继续自动采集 2428 已有字段：

- `bundle_id`
- `app_executable`
- 主程序 Mach-O `LC_UUID`
- `app_version`
- `app_build`

服务器只允许已解析 IPA 且存在 active `fa_ipa_category_binding` 的身份参与 `scope=3` 高级授权。客户端不上传、也不决定 `app_id`。

## 6. Endpoints

Runtime Config：

```text
GET /index/dylib_verify/config?dylib_key=zonoe.main
```

一次性 Challenge：

```text
POST /index/dylib_verify/challenge
Content-Type: application/json
```

在线验证：

```text
POST /index/dylib_verify/verify
Content-Type: application/json
```

## 7. Device proof — Protocol v3

每台设备第一次使用当前 Dylib 时生成独立 P-256 KeyPair：

- Private Key：iOS Keychain，`ThisDeviceOnly`
- Public Key：SPKI PEM，可登记到服务器
- 不存在全局共享客户端密钥

每次 Verify 前服务器返回短期一次性 Challenge。客户端按固定顺序构造：

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

使用设备 P-256 私钥执行 ECDSA-SHA256。`SecKeyCreateSignature` 返回 DER signature，传输时 Base64。

首次公钥登记额外提交当前已激活卡密；服务端使用已有 `AuthorizationLicense` 逻辑确认卡密与同一 UDID 的绑定关系。后续验证不再重复提交卡密。

## 8. Runtime Config signature and server migration

SDK 按原 2428 顺序获取可用验证地址：

1. 工程中预置的多个 `bootstrapURLs`
2. 上次验签成功配置中下发的新 Bootstrap 地址
3. 已验签的 Last-Known-Good 运行配置
4. 配置中的多个 `api_endpoints`
5. 老的 `endpointURL` 直接兜底
6. 全部网络入口不可用时，才尝试未过期的离线授权缓存

Bootstrap/Runtime Config 改为服务器 RSA-2048 + SHA-256 签名。客户端只嵌入服务器 RSA 公钥和 Key ID；服务器私钥保存在服务器 `runtime/dylib_auth/`，不会进入数据库、Git 仓库或生成的 Dylib 工程。

物理边界仍然存在：如果所有预置 Bootstrap、所有缓存过的 Bootstrap、旧 endpoint 同时永久失效，并且设备从未拿到可用 Last-Known-Good 配置，则旧二进制不可能凭空知道未来的新服务器地址。因此生产环境仍建议让 Bootstrap 分散到至少两个独立域名/托管渠道。

## 9. Offline behavior

在线验证成功后，授权结果继续存入 iOS Keychain：

- `access_level`
- `permissions`
- `app_identity`
- `app_update`
- session token
- `offline_grace_seconds`

网络/服务器短暂不可用时，仅在 `verified_at + offline_grace_seconds` 内恢复这份结果。服务端明确拒绝时会清除当前 BundleID 的离线授权，而不会拿旧结果覆盖拒绝。

scope=3 的服务端 session token 仍绑定服务器识别出的 App ID、access level 和 Mach-O UUID，并额外记录 Device Key ID。

## 10. Security boundary

Secretless v3 解决的是“一个 Dylib 内嵌全局共享密钥，一次泄漏影响所有安装”的问题。它不把被注入/越狱进程变成可信执行环境；攻击者若完全控制进程，仍可能调用本机 Keychain 私钥完成签名。

最终授权边界继续由服务端的 UDID、卡密 scope、解析 App 身份、Dylib 版本/SHA256、一次性 Challenge、设备公钥绑定、黑名单和服务器状态共同决定。
