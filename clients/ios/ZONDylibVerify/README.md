# ZONDylibVerify Objective-C Client

`ZONDylibVerify` 用于注入式 Objective-C / Objective-C++ Dylib 的运行时授权。2406 把“Dylib 是否有效”和“当前用户在当前 App 中拥有什么权限”拆成两层，同时增加 App 身份识别、游戏更新提示、远程通知和可迁移服务器发现。

它不会替换旧的 `Index::dylib()` / `Index::apiface()`。

## 1. Required frameworks

- Foundation.framework
- Security.framework
- CommonCrypto
- libdl / dyld Mach-O runtime

## 2. Configure once

```objc
ZONVerifyConfiguration *cfg = [ZONVerifyConfiguration new];

// 建议至少两个相互独立的 Bootstrap 地址。它们只负责告诉旧 Dylib
// 当前真正的 API 地址在哪里，避免未来换服务器/域名必须重发 Dylib。
cfg.bootstrapURLs = @[
    [NSURL URLWithString:@"https://bootstrap-a.example.com/index/dylib_verify/config"],
    [NSURL URLWithString:@"https://bootstrap-b.example.net/index/dylib_verify/config"]
];

// 旧/最后兜底地址；Bootstrap/LKG 都失败时仍会尝试它。
cfg.endpointURL = [NSURL URLWithString:@"https://api.example.com/index/dylib_verify/verify"];

cfg.dylibKey = @"zonoe.main";
cfg.dylibVersion = @"1.0.0";
cfg.dylibBuild = @"1";
cfg.verifySecret = @"<Dylib 验证中心生成的验证密钥>";
cfg.requestTimeout = 10.0;
cfg.udidProvider = ^NSString * _Nullable{
    return ExistingProjectUDID();
};
```

`udidProvider` 必须继续返回项目现有的真实 UDID。SDK 不生成替代设备标识。

## 3. Verify and render permissions

```objc
ZONVerifyClient *client = [[ZONVerifyClient alloc] initWithConfiguration:cfg];
[client verifyWithCompletion:^(ZONVerifyResult *result) {
    if (!result.allowed) {
        // 按 result.action / result.message 处理失败。
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
        // title/message/button/download_url 都由服务器下发；客户端只负责渲染。
    }

    if (result.notice) {
        // 可按 notice_key + revision 做“同一版本只显示一次”等本地策略。
    }
}];
```

## 4. Three card scopes

服务端负责计算，不建议客户端自行推断卡密类型：

| card_scope | 条件 | access_level | 权限 |
| --- | --- | --- | --- |
| `2` 仅验证 | 任意 App | `basic` | 普通菜单 |
| `3` 指定 App | 当前 App 身份命中 `fa_kami_app.app_id` | `app_plus` | 普通 + 额外菜单/功能 |
| `1` 全软件源 | 任意 App | `global_plus` | 普通 + 额外菜单/功能 |

同一 UDID 有多张有效卡时，当前 App 下取最高适用权限：`global_plus > app_plus > basic > block`。

云存档等独立购买 entitlement 不属于这个等级，不应因为菜单授权通过而自动放行。

## 5. App identity

Protocol v2 自动采集：

- `bundle_id`
- `app_executable`
- 主程序 Mach-O `LC_UUID`
- `app_version`
- `app_build`

服务器只允许已解析 IPA 且存在 active `fa_ipa_category_binding` 的身份参与 `scope=3` 高级授权。客户端不上传、也不决定 `app_id`。

因此只修改 Info.plist 的 BundleID 不足以把另一款游戏伪装成已购买的指定 App。

## 6. Endpoints

运行配置发现：

```text
GET /index/dylib_verify/config?dylib_key=zonoe.main
```

在线验证：

```text
POST /index/dylib_verify/verify
Content-Type: application/json
```

## 7. Request signing

### Protocol v1 (legacy, unchanged)

```text
udid
bundle_id
dylib_key
dylib_version
dylib_build
dylib_sha256
timestamp
nonce
```

### Protocol v2

v2 保留完整 v1 原文，并在末尾追加：

```text
protocol_version
app_executable
app_macho_uuid
app_version
app_build
```

整个 UTF-8 文本使用 per-Dylib `verifySecret` 做 HMAC-SHA256，`signature` 为小写 64 位十六进制。

旧 v1 客户端仍可进行 Dylib/UDID/版本验证：全软件源卡可得到 `global_plus`，仅验证卡可得到 `basic`；指定 App 卡需要 v2 App 身份后才能得到 `app_plus`。

## 8. Runtime server migration

SDK 按下面顺序获取可用验证地址：

1. 工程中预置的多个 `bootstrapURLs`
2. 上次验签成功配置中下发的新 Bootstrap 地址
3. 通过 HMAC 校验的 Last-Known-Good 运行配置
4. 配置中的多个 `api_endpoints`
5. 老的 `endpointURL` 直接兜底
6. 全部网络入口不可用时，才尝试未过期的离线授权缓存

Bootstrap 返回的运行配置也使用同一个 per-Dylib `verifySecret` 做 HMAC-SHA256 验签，客户端不会接受未签名/签名不符的 API 地址。

物理边界仍然存在：如果所有预置 Bootstrap、所有缓存过的 Bootstrap、旧 endpoint 同时永久失效，并且设备从未拿到可用 Last-Known-Good 配置，则旧二进制不可能凭空知道未来的新服务器地址。因此生产环境应让 Bootstrap 分散到至少两个独立域名/托管渠道。

## 9. Offline behavior

在线验证成功后，授权结果存入 iOS Keychain：

- `access_level`
- `permissions`
- `app_identity`
- `app_update`
- session token
- `offline_grace_seconds`

网络/服务器短暂不可用时，仅在 `verified_at + offline_grace_seconds` 内恢复这份结果。服务端明确拒绝时会清除当前 BundleID 的离线授权，而不会拿旧结果覆盖拒绝。

scope=3 的服务端 session token 还绑定服务器识别出的 App ID、access level 和 Mach-O UUID，避免把某 App 的高级授权 session 当作另一款 App 的授权。

## 10. Security boundary

HMAC secret、Mach-O UUID 和本地指纹都能显著提高普通篡改成本，但注入/越狱环境仍属于客户端可控环境，不应把客户端当作硬件可信执行环境。最终授权边界必须继续由服务端的 UDID、卡密 scope、解析 App 身份、Dylib 版本、nonce/replay、黑名单和服务器状态共同决定。
