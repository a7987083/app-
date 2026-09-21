#import "ZONVerifyClient.h"
#import <CommonCrypto/CommonDigest.h>
#import <CommonCrypto/CommonHMAC.h>
#import <Security/Security.h>
#import <dlfcn.h>

static void ZONVerifyImageAnchor(void) {}

@implementation ZONVerifyConfiguration
- (instancetype)init
{
    self = [super init];
    if (self) {
        _dylibBuild = @"";
        _requestTimeout = 10.0;
    }
    return self;
}
@end

@implementation ZONVerifyResult
- (instancetype)init
{
    self = [super init];
    if (self) {
        _code = @"unknown";
        _action = @"disable_feature";
        _message = @"";
        _token = @"";
    }
    return self;
}
@end

@interface ZONVerifyClient ()
@property (nonatomic, strong) ZONVerifyConfiguration *configuration;
@property (nonatomic, strong) NSURLSession *session;
@end

@implementation ZONVerifyClient

- (instancetype)initWithConfiguration:(ZONVerifyConfiguration *)configuration
{
    NSParameterAssert(configuration);
    self = [super init];
    if (self) {
        _configuration = configuration;
        NSURLSessionConfiguration *sessionConfiguration = [NSURLSessionConfiguration ephemeralSessionConfiguration];
        sessionConfiguration.timeoutIntervalForRequest = MAX(3.0, configuration.requestTimeout);
        sessionConfiguration.timeoutIntervalForResource = MAX(5.0, configuration.requestTimeout + 5.0);
        _session = [NSURLSession sessionWithConfiguration:sessionConfiguration];
    }
    return self;
}

- (void)verifyWithCompletion:(void (^)(ZONVerifyResult *result))completion
{
    if (!completion) {
        return;
    }

    NSString *udid = self.configuration.udidProvider ? self.configuration.udidProvider() : nil;
    NSString *bundleID = NSBundle.mainBundle.bundleIdentifier ?: @"";
    if (udid.length == 0) {
        completion([self resultAllowed:NO code:@"udid_missing" action:@"block" message:@"UDID unavailable"]);
        return;
    }
    if (bundleID.length == 0 || self.configuration.endpointURL == nil || self.configuration.dylibKey.length == 0 ||
        self.configuration.dylibVersion.length == 0 || self.configuration.verifySecret.length < 32) {
        completion([self resultAllowed:NO code:@"client_config_invalid" action:@"block" message:@"Verification client configuration is incomplete"]);
        return;
    }

    NSString *sha256 = [ZONVerifyClient currentDylibSHA256];
    NSTimeInterval now = floor([[NSDate date] timeIntervalSince1970]);
    NSString *nonce = [self randomHexBytes:24];
    NSString *canonical = [self canonicalUDID:udid
                                     bundleID:bundleID
                                     dylibKey:self.configuration.dylibKey
                                      version:self.configuration.dylibVersion
                                        build:self.configuration.dylibBuild ?: @""
                                       sha256:sha256 ?: @""
                                    timestamp:(NSInteger)now
                                        nonce:nonce];
    NSString *signature = [self hmacSHA256Hex:canonical key:self.configuration.verifySecret];

    NSDictionary *payload = @{
        @"udid": udid,
        @"bundle_id": bundleID,
        @"dylib_key": self.configuration.dylibKey,
        @"dylib_version": self.configuration.dylibVersion,
        @"dylib_build": self.configuration.dylibBuild ?: @"",
        @"dylib_sha256": sha256 ?: @"",
        @"timestamp": @((NSInteger)now),
        @"nonce": nonce,
        @"signature": signature
    };

    NSError *jsonError = nil;
    NSData *body = [NSJSONSerialization dataWithJSONObject:payload options:0 error:&jsonError];
    if (!body) {
        completion([self resultAllowed:NO code:@"json_error" action:@"disable_feature" message:jsonError.localizedDescription ?: @"Unable to encode request"]);
        return;
    }

    NSMutableURLRequest *request = [NSMutableURLRequest requestWithURL:self.configuration.endpointURL];
    request.HTTPMethod = @"POST";
    request.HTTPBody = body;
    [request setValue:@"application/json" forHTTPHeaderField:@"Content-Type"];
    [request setValue:@"application/json" forHTTPHeaderField:@"Accept"];

    __weak typeof(self) weakSelf = self;
    NSURLSessionDataTask *task = [self.session dataTaskWithRequest:request completionHandler:^(NSData * _Nullable data, NSURLResponse * _Nullable response, NSError * _Nullable error) {
        __strong typeof(weakSelf) strongSelf = weakSelf;
        if (!strongSelf) {
            return;
        }

        NSHTTPURLResponse *http = [response isKindOfClass:NSHTTPURLResponse.class] ? (NSHTTPURLResponse *)response : nil;
        if (error || http.statusCode >= 500 || data.length == 0) {
            ZONVerifyResult *cached = [strongSelf validOfflineCacheForBundleID:bundleID];
            if (cached) {
                completion(cached);
                return;
            }
            NSString *message = error.localizedDescription ?: @"Verification service unavailable";
            completion([strongSelf resultAllowed:NO code:@"network_unavailable" action:@"disable_feature" message:message]);
            return;
        }

        NSError *decodeError = nil;
        NSDictionary *json = [NSJSONSerialization JSONObjectWithData:data options:0 error:&decodeError];
        if (![json isKindOfClass:NSDictionary.class]) {
            completion([strongSelf resultAllowed:NO code:@"response_invalid" action:@"disable_feature" message:decodeError.localizedDescription ?: @"Invalid verification response"]);
            return;
        }

        ZONVerifyResult *result = [strongSelf resultFromJSON:json];
        if (result.isAllowed) {
            [strongSelf storeOfflineCacheForResult:result bundleID:bundleID];
        } else {
            [strongSelf clearOfflineCacheForBundleID:bundleID];
        }
        completion(result);
    }];
    [task resume];
}

+ (NSString *)currentDylibSHA256
{
    Dl_info info;
    if (dladdr((const void *)&ZONVerifyImageAnchor, &info) == 0 || !info.dli_fname) {
        return @"";
    }
    NSString *path = [NSString stringWithUTF8String:info.dli_fname];
    NSInputStream *stream = [NSInputStream inputStreamWithFileAtPath:path];
    [stream open];
    CC_SHA256_CTX ctx;
    CC_SHA256_Init(&ctx);
    uint8_t buffer[64 * 1024];
    NSInteger read = 0;
    while ((read = [stream read:buffer maxLength:sizeof(buffer)]) > 0) {
        CC_SHA256_Update(&ctx, buffer, (CC_LONG)read);
    }
    [stream close];
    if (read < 0) {
        return @"";
    }
    unsigned char digest[CC_SHA256_DIGEST_LENGTH];
    CC_SHA256_Final(digest, &ctx);
    NSMutableString *hex = [NSMutableString stringWithCapacity:CC_SHA256_DIGEST_LENGTH * 2];
    for (NSUInteger i = 0; i < CC_SHA256_DIGEST_LENGTH; i++) {
        [hex appendFormat:@"%02x", digest[i]];
    }
    return hex;
}

- (NSString *)canonicalUDID:(NSString *)udid
                   bundleID:(NSString *)bundleID
                   dylibKey:(NSString *)dylibKey
                    version:(NSString *)version
                      build:(NSString *)build
                     sha256:(NSString *)sha256
                  timestamp:(NSInteger)timestamp
                      nonce:(NSString *)nonce
{
    return [@[udid ?: @"", bundleID ?: @"", dylibKey ?: @"", version ?: @"", build ?: @"", sha256.lowercaseString ?: @"", [NSString stringWithFormat:@"%ld", (long)timestamp], nonce ?: @""] componentsJoinedByString:@"\n"];
}

- (NSString *)hmacSHA256Hex:(NSString *)message key:(NSString *)key
{
    NSData *keyData = [key dataUsingEncoding:NSUTF8StringEncoding];
    NSData *messageData = [message dataUsingEncoding:NSUTF8StringEncoding];
    unsigned char digest[CC_SHA256_DIGEST_LENGTH];
    CCHmac(kCCHmacAlgSHA256, keyData.bytes, keyData.length, messageData.bytes, messageData.length, digest);
    NSMutableString *hex = [NSMutableString stringWithCapacity:CC_SHA256_DIGEST_LENGTH * 2];
    for (NSUInteger i = 0; i < CC_SHA256_DIGEST_LENGTH; i++) {
        [hex appendFormat:@"%02x", digest[i]];
    }
    return hex;
}

- (NSString *)randomHexBytes:(NSUInteger)count
{
    NSMutableData *data = [NSMutableData dataWithLength:count];
    if (SecRandomCopyBytes(kSecRandomDefault, count, data.mutableBytes) != errSecSuccess) {
        return [NSUUID UUID].UUIDString.lowercaseString;
    }
    const uint8_t *bytes = data.bytes;
    NSMutableString *hex = [NSMutableString stringWithCapacity:count * 2];
    for (NSUInteger i = 0; i < count; i++) {
        [hex appendFormat:@"%02x", bytes[i]];
    }
    return hex;
}

- (ZONVerifyResult *)resultFromJSON:(NSDictionary *)json
{
    ZONVerifyResult *result = [ZONVerifyResult new];
    result.allowed = [json[@"ok"] boolValue];
    result.code = [json[@"code"] isKindOfClass:NSString.class] ? json[@"code"] : @"unknown";
    result.action = [json[@"action"] isKindOfClass:NSString.class] ? json[@"action"] : @"disable_feature";
    result.message = [json[@"message"] isKindOfClass:NSString.class] ? json[@"message"] : @"";
    result.token = [json[@"token"] isKindOfClass:NSString.class] ? json[@"token"] : @"";
    result.offlineGraceSeconds = [json[@"offline_grace_seconds"] doubleValue];
    result.serverTime = [json[@"server_time"] doubleValue];
    return result;
}

- (ZONVerifyResult *)resultAllowed:(BOOL)allowed code:(NSString *)code action:(NSString *)action message:(NSString *)message
{
    ZONVerifyResult *result = [ZONVerifyResult new];
    result.allowed = allowed;
    result.code = code ?: @"unknown";
    result.action = action ?: @"disable_feature";
    result.message = message ?: @"";
    return result;
}

- (NSString *)cacheService
{
    return [NSString stringWithFormat:@"com.zonoe.dylib.verify.%@", self.configuration.dylibKey ?: @"unknown"];
}

- (void)storeOfflineCacheForResult:(ZONVerifyResult *)result bundleID:(NSString *)bundleID
{
    NSDictionary *cache = @{
        @"verified_at": @([[NSDate date] timeIntervalSince1970]),
        @"offline_grace_seconds": @(MAX(0, result.offlineGraceSeconds)),
        @"token": result.token ?: @"",
        @"code": result.code ?: @"ok",
        @"message": result.message ?: @""
    };
    NSData *data = [NSJSONSerialization dataWithJSONObject:cache options:0 error:nil];
    if (!data) return;

    NSDictionary *query = @{
        (__bridge id)kSecClass: (__bridge id)kSecClassGenericPassword,
        (__bridge id)kSecAttrService: [self cacheService],
        (__bridge id)kSecAttrAccount: bundleID
    };
    SecItemDelete((__bridge CFDictionaryRef)query);
    NSMutableDictionary *add = [query mutableCopy];
    add[(__bridge id)kSecValueData] = data;
    add[(__bridge id)kSecAttrAccessible] = (__bridge id)kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly;
    SecItemAdd((__bridge CFDictionaryRef)add, NULL);
}

- (ZONVerifyResult *)validOfflineCacheForBundleID:(NSString *)bundleID
{
    NSMutableDictionary *query = [@{
        (__bridge id)kSecClass: (__bridge id)kSecClassGenericPassword,
        (__bridge id)kSecAttrService: [self cacheService],
        (__bridge id)kSecAttrAccount: bundleID,
        (__bridge id)kSecReturnData: @YES,
        (__bridge id)kSecMatchLimit: (__bridge id)kSecMatchLimitOne
    } mutableCopy];
    CFTypeRef resultRef = NULL;
    OSStatus status = SecItemCopyMatching((__bridge CFDictionaryRef)query, &resultRef);
    if (status != errSecSuccess || !resultRef) {
        return nil;
    }
    NSData *data = CFBridgingRelease(resultRef);
    NSDictionary *cache = [NSJSONSerialization JSONObjectWithData:data options:0 error:nil];
    if (![cache isKindOfClass:NSDictionary.class]) {
        return nil;
    }
    NSTimeInterval verifiedAt = [cache[@"verified_at"] doubleValue];
    NSTimeInterval grace = MIN(86400.0, MAX(0.0, [cache[@"offline_grace_seconds"] doubleValue]));
    NSTimeInterval now = [[NSDate date] timeIntervalSince1970];
    if (verifiedAt <= 0 || grace <= 0 || now > verifiedAt + grace) {
        [self clearOfflineCacheForBundleID:bundleID];
        return nil;
    }
    ZONVerifyResult *result = [ZONVerifyResult new];
    result.allowed = YES;
    result.code = @"offline_grace";
    result.action = @"allow";
    result.message = [cache[@"message"] isKindOfClass:NSString.class] ? cache[@"message"] : @"Offline grace active";
    result.token = [cache[@"token"] isKindOfClass:NSString.class] ? cache[@"token"] : @"";
    result.offlineGraceSeconds = grace;
    result.offlineCache = YES;
    return result;
}

- (void)clearOfflineCacheForBundleID:(NSString *)bundleID
{
    NSDictionary *query = @{
        (__bridge id)kSecClass: (__bridge id)kSecClassGenericPassword,
        (__bridge id)kSecAttrService: [self cacheService],
        (__bridge id)kSecAttrAccount: bundleID
    };
    SecItemDelete((__bridge CFDictionaryRef)query);
}

@end
