#import "ZONVerifyClient.h"
#import <CommonCrypto/CommonDigest.h>
#import <CommonCrypto/CommonHMAC.h>
#import <Security/Security.h>
#import <dlfcn.h>
#import <mach-o/dyld.h>
#import <mach-o/loader.h>

static void ZONVerifyImageAnchor(void) {}
static NSString * const ZONRuntimeConfigAccount = @"runtime-config";
static NSTimeInterval const ZONRuntimeConfigMaxStale = 30.0 * 24.0 * 60.0 * 60.0;

@implementation ZONVerifyConfiguration
- (instancetype)init
{
    self = [super init];
    if (self) {
        _dylibBuild = @"";
        _bootstrapURLs = @[];
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
        _accessLevel = @"block";
        _permissions = @{};
        _appIdentity = @{};
        _appUpdate = @{ @"available": @NO };
        _protocolVersion = 1;
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
    if (!completion) return;

    NSString *udid = self.configuration.udidProvider ? self.configuration.udidProvider() : nil;
    NSString *bundleID = NSBundle.mainBundle.bundleIdentifier ?: @"";
    NSString *executable = [NSBundle.mainBundle objectForInfoDictionaryKey:@"CFBundleExecutable"] ?: @"";
    NSString *appVersion = [NSBundle.mainBundle objectForInfoDictionaryKey:@"CFBundleShortVersionString"] ?: @"";
    NSString *appBuild = [NSBundle.mainBundle objectForInfoDictionaryKey:@"CFBundleVersion"] ?: @"";
    NSString *appUUID = [ZONVerifyClient currentAppMachOUUID] ?: @"";

    if (udid.length == 0) {
        completion([self resultAllowed:NO code:@"udid_missing" action:@"block" message:@"UDID unavailable"]);
        return;
    }
    BOOL hasDiscovery = self.configuration.bootstrapURLs.count > 0 || self.configuration.endpointURL != nil;
    if (bundleID.length == 0 || !hasDiscovery || self.configuration.dylibKey.length == 0 ||
        self.configuration.dylibVersion.length == 0 || self.configuration.verifySecret.length < 32 ||
        executable.length == 0 || appUUID.length == 0) {
        completion([self resultAllowed:NO code:@"client_config_invalid" action:@"block" message:@"Verification client configuration is incomplete"]);
        return;
    }

    NSString *sha256 = [ZONVerifyClient currentDylibSHA256];
    NSTimeInterval now = floor([[NSDate date] timeIntervalSince1970]);
    NSString *nonce = [self randomHexBytes:24];
    NSInteger protocolVersion = 2;
    NSString *canonical = [self canonicalV2UDID:udid
                                       bundleID:bundleID
                                       dylibKey:self.configuration.dylibKey
                                        version:self.configuration.dylibVersion
                                          build:self.configuration.dylibBuild ?: @""
                                         sha256:sha256 ?: @""
                                      timestamp:(NSInteger)now
                                          nonce:nonce
                                protocolVersion:protocolVersion
                                     executable:executable
                                      machoUUID:appUUID
                                     appVersion:appVersion
                                       appBuild:appBuild];
    NSString *signature = [self hmacSHA256Hex:canonical key:self.configuration.verifySecret];

    NSDictionary *payload = @{
        @"protocol_version": @(protocolVersion),
        @"udid": udid,
        @"bundle_id": bundleID,
        @"dylib_key": self.configuration.dylibKey,
        @"dylib_version": self.configuration.dylibVersion,
        @"dylib_build": self.configuration.dylibBuild ?: @"",
        @"dylib_sha256": sha256 ?: @"",
        @"app_executable": executable,
        @"app_macho_uuid": appUUID,
        @"app_version": appVersion,
        @"app_build": appBuild,
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

    __weak typeof(self) weakSelf = self;
    [self resolveVerificationEndpointsWithCompletion:^(NSArray<NSURL *> *endpoints) {
        __strong typeof(weakSelf) strongSelf = weakSelf;
        if (!strongSelf) return;
        if (endpoints.count == 0) {
            ZONVerifyResult *cached = [strongSelf validOfflineCacheForBundleID:bundleID];
            completion(cached ?: [strongSelf resultAllowed:NO code:@"network_unavailable" action:@"disable_feature" message:@"No verification endpoint available"]);
            return;
        }
        [strongSelf sendVerificationBody:body endpoints:endpoints index:0 bundleID:bundleID completion:completion];
    }];
}

- (void)sendVerificationBody:(NSData *)body
                    endpoints:(NSArray<NSURL *> *)endpoints
                        index:(NSUInteger)index
                     bundleID:(NSString *)bundleID
                   completion:(void (^)(ZONVerifyResult *result))completion
{
    if (index >= endpoints.count) {
        ZONVerifyResult *cached = [self validOfflineCacheForBundleID:bundleID];
        completion(cached ?: [self resultAllowed:NO code:@"network_unavailable" action:@"disable_feature" message:@"Verification service unavailable"]);
        return;
    }

    NSMutableURLRequest *request = [NSMutableURLRequest requestWithURL:endpoints[index]];
    request.HTTPMethod = @"POST";
    request.HTTPBody = body;
    [request setValue:@"application/json" forHTTPHeaderField:@"Content-Type"];
    [request setValue:@"application/json" forHTTPHeaderField:@"Accept"];

    __weak typeof(self) weakSelf = self;
    NSURLSessionDataTask *task = [self.session dataTaskWithRequest:request completionHandler:^(NSData * _Nullable data, NSURLResponse * _Nullable response, NSError * _Nullable error) {
        __strong typeof(weakSelf) strongSelf = weakSelf;
        if (!strongSelf) return;
        NSHTTPURLResponse *http = [response isKindOfClass:NSHTTPURLResponse.class] ? (NSHTTPURLResponse *)response : nil;
        if (error || http.statusCode >= 500 || data.length == 0) {
            [strongSelf sendVerificationBody:body endpoints:endpoints index:index + 1 bundleID:bundleID completion:completion];
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

#pragma mark - Runtime endpoint discovery

- (void)resolveVerificationEndpointsWithCompletion:(void (^)(NSArray<NSURL *> *endpoints))completion
{
    NSDictionary *cached = [self cachedRuntimeConfigAllowStale:YES];
    NSMutableArray<NSURL *> *bootstrap = [NSMutableArray array];
    for (NSURL *url in self.configuration.bootstrapURLs ?: @[]) {
        if ([url isKindOfClass:NSURL.class] && ![bootstrap containsObject:url]) [bootstrap addObject:url];
    }
    NSArray *cachedBootstrap = [cached[@"bootstrap_urls"] isKindOfClass:NSArray.class] ? cached[@"bootstrap_urls"] : @[];
    for (id value in cachedBootstrap) {
        NSURL *url = [value isKindOfClass:NSString.class] ? [NSURL URLWithString:value] : nil;
        if (url && ![bootstrap containsObject:url]) [bootstrap addObject:url];
    }

    if (bootstrap.count == 0) {
        completion([self verificationEndpointsFromRuntimeConfig:cached]);
        return;
    }

    __weak typeof(self) weakSelf = self;
    [self fetchBootstrapURLs:bootstrap index:0 completion:^(NSDictionary * _Nullable config) {
        __strong typeof(weakSelf) strongSelf = weakSelf;
        if (!strongSelf) return;
        NSDictionary *selected = config ?: cached;
        NSArray<NSURL *> *endpoints = [strongSelf verificationEndpointsFromRuntimeConfig:selected];
        completion(endpoints);
    }];
}

- (void)fetchBootstrapURLs:(NSArray<NSURL *> *)urls
                     index:(NSUInteger)index
                completion:(void (^)(NSDictionary * _Nullable config))completion
{
    if (index >= urls.count) {
        completion(nil);
        return;
    }
    NSURLComponents *components = [NSURLComponents componentsWithURL:urls[index] resolvingAgainstBaseURL:NO];
    NSMutableArray<NSURLQueryItem *> *items = [NSMutableArray arrayWithArray:components.queryItems ?: @[]];
    [items addObject:[NSURLQueryItem queryItemWithName:@"dylib_key" value:self.configuration.dylibKey]];
    components.queryItems = items;
    NSURL *url = components.URL;
    if (!url) {
        [self fetchBootstrapURLs:urls index:index + 1 completion:completion];
        return;
    }

    NSMutableURLRequest *request = [NSMutableURLRequest requestWithURL:url];
    request.HTTPMethod = @"GET";
    [request setValue:@"application/json" forHTTPHeaderField:@"Accept"];
    __weak typeof(self) weakSelf = self;
    NSURLSessionDataTask *task = [self.session dataTaskWithRequest:request completionHandler:^(NSData * _Nullable data, NSURLResponse * _Nullable response, NSError * _Nullable error) {
        __strong typeof(weakSelf) strongSelf = weakSelf;
        if (!strongSelf) return;
        NSHTTPURLResponse *http = [response isKindOfClass:NSHTTPURLResponse.class] ? (NSHTTPURLResponse *)response : nil;
        if (error || http.statusCode >= 400 || data.length == 0) {
            [strongSelf fetchBootstrapURLs:urls index:index + 1 completion:completion];
            return;
        }
        NSDictionary *json = [NSJSONSerialization JSONObjectWithData:data options:0 error:nil];
        if (![strongSelf validateRuntimeConfig:json allowStale:NO]) {
            [strongSelf fetchBootstrapURLs:urls index:index + 1 completion:completion];
            return;
        }
        NSMutableDictionary *stored = [json mutableCopy];
        stored[@"received_at"] = @([[NSDate date] timeIntervalSince1970]);
        [strongSelf storeRuntimeConfig:stored];
        completion(stored);
    }];
    [task resume];
}

- (NSArray<NSURL *> *)verificationEndpointsFromRuntimeConfig:(NSDictionary *)config
{
    NSMutableArray<NSURL *> *result = [NSMutableArray array];
    if ([self validateRuntimeConfig:config allowStale:YES]) {
        NSArray *bases = [config[@"api_endpoints"] isKindOfClass:NSArray.class] ? config[@"api_endpoints"] : @[];
        NSString *path = [config[@"verify_path"] isKindOfClass:NSString.class] ? config[@"verify_path"] : @"/index/dylib_verify/verify";
        for (id value in bases) {
            if (![value isKindOfClass:NSString.class]) continue;
            NSString *base = [(NSString *)value stringByTrimmingCharactersInSet:[NSCharacterSet whitespaceAndNewlineCharacterSet]];
            while ([base hasSuffix:@"/"]) base = [base substringToIndex:base.length - 1];
            NSString *normalizedPath = [path hasPrefix:@"/"] ? path : [@"/" stringByAppendingString:path];
            NSURL *url = [NSURL URLWithString:[base stringByAppendingString:normalizedPath]];
            if (url && ![result containsObject:url]) [result addObject:url];
        }
    }
    if (self.configuration.endpointURL && ![result containsObject:self.configuration.endpointURL]) {
        [result addObject:self.configuration.endpointURL];
    }
    return result;
}

- (BOOL)validateRuntimeConfig:(NSDictionary *)config allowStale:(BOOL)allowStale
{
    if (![config isKindOfClass:NSDictionary.class] || ![config[@"ok"] boolValue]) return NO;
    NSArray *apis = [config[@"api_endpoints"] isKindOfClass:NSArray.class] ? config[@"api_endpoints"] : nil;
    NSArray *bootstraps = [config[@"bootstrap_urls"] isKindOfClass:NSArray.class] ? config[@"bootstrap_urls"] : nil;
    NSString *path = [config[@"verify_path"] isKindOfClass:NSString.class] ? config[@"verify_path"] : nil;
    NSString *signature = [config[@"signature"] isKindOfClass:NSString.class] ? config[@"signature"] : nil;
    NSInteger version = [config[@"config_version"] integerValue];
    NSTimeInterval expires = [config[@"expires_at"] doubleValue];
    if (!apis || !bootstraps || path.length == 0 || signature.length != 64 || version < 1 || expires <= 0) return NO;

    NSTimeInterval now = [[NSDate date] timeIntervalSince1970];
    if (!allowStale && expires < now) return NO;
    if (allowStale && expires < now) {
        NSTimeInterval received = [config[@"received_at"] doubleValue];
        if (received <= 0 || now - received > ZONRuntimeConfigMaxStale) return NO;
    }

    NSString *canonical = [self canonicalRuntimeConfigVersion:version apiEndpoints:apis bootstrapURLs:bootstraps verifyPath:path expiresAt:(NSInteger)expires];
    NSString *expected = [self hmacSHA256Hex:canonical key:self.configuration.verifySecret];
    return [self constantTimeHex:expected equals:signature.lowercaseString];
}

- (NSString *)canonicalRuntimeConfigVersion:(NSInteger)version
                                apiEndpoints:(NSArray *)apiEndpoints
                               bootstrapURLs:(NSArray *)bootstrapURLs
                                  verifyPath:(NSString *)verifyPath
                                   expiresAt:(NSInteger)expiresAt
{
    return [@[ [NSString stringWithFormat:@"%ld", (long)version],
               [apiEndpoints componentsJoinedByString:@","],
               [bootstrapURLs componentsJoinedByString:@","],
               verifyPath ?: @"",
               [NSString stringWithFormat:@"%ld", (long)expiresAt] ] componentsJoinedByString:@"\n"];
}

- (BOOL)constantTimeHex:(NSString *)left equals:(NSString *)right
{
    NSData *a = [left.lowercaseString dataUsingEncoding:NSUTF8StringEncoding];
    NSData *b = [right.lowercaseString dataUsingEncoding:NSUTF8StringEncoding];
    if (a.length != b.length || a.length == 0) return NO;
    const uint8_t *ab = a.bytes;
    const uint8_t *bb = b.bytes;
    uint8_t diff = 0;
    for (NSUInteger i = 0; i < a.length; i++) diff |= ab[i] ^ bb[i];
    return diff == 0;
}

#pragma mark - App identity

+ (NSString *)currentAppMachOUUID
{
    const struct mach_header *header = _dyld_get_image_header(0);
    if (!header) return @"";
    uint32_t magic = header->magic;
    BOOL is64 = (magic == MH_MAGIC_64 || magic == MH_CIGAM_64);
    uintptr_t cursor = (uintptr_t)header + (is64 ? sizeof(struct mach_header_64) : sizeof(struct mach_header));
    uint32_t ncmds = header->ncmds;
    for (uint32_t i = 0; i < ncmds; i++) {
        const struct load_command *command = (const struct load_command *)cursor;
        if (!command || command->cmdsize < sizeof(struct load_command)) break;
        if ((command->cmd & 0x7fffffff) == LC_UUID && command->cmdsize >= sizeof(struct uuid_command)) {
            const struct uuid_command *uuidCommand = (const struct uuid_command *)command;
            const uint8_t *u = uuidCommand->uuid;
            return [NSString stringWithFormat:@"%02X%02X%02X%02X-%02X%02X-%02X%02X-%02X%02X-%02X%02X%02X%02X%02X%02X",
                    u[0],u[1],u[2],u[3],u[4],u[5],u[6],u[7],u[8],u[9],u[10],u[11],u[12],u[13],u[14],u[15]];
        }
        cursor += command->cmdsize;
    }
    return @"";
}

+ (NSString *)currentDylibSHA256
{
    Dl_info info;
    if (dladdr((const void *)&ZONVerifyImageAnchor, &info) == 0 || !info.dli_fname) return @"";
    NSString *path = [NSString stringWithUTF8String:info.dli_fname];
    NSInputStream *stream = [NSInputStream inputStreamWithFileAtPath:path];
    [stream open];
    CC_SHA256_CTX ctx;
    CC_SHA256_Init(&ctx);
    uint8_t buffer[64 * 1024];
    NSInteger read = 0;
    while ((read = [stream read:buffer maxLength:sizeof(buffer)]) > 0) CC_SHA256_Update(&ctx, buffer, (CC_LONG)read);
    [stream close];
    if (read < 0) return @"";
    unsigned char digest[CC_SHA256_DIGEST_LENGTH];
    CC_SHA256_Final(digest, &ctx);
    NSMutableString *hex = [NSMutableString stringWithCapacity:CC_SHA256_DIGEST_LENGTH * 2];
    for (NSUInteger i = 0; i < CC_SHA256_DIGEST_LENGTH; i++) [hex appendFormat:@"%02x", digest[i]];
    return hex;
}

#pragma mark - Signing

- (NSString *)canonicalV2UDID:(NSString *)udid
                     bundleID:(NSString *)bundleID
                     dylibKey:(NSString *)dylibKey
                      version:(NSString *)version
                        build:(NSString *)build
                       sha256:(NSString *)sha256
                    timestamp:(NSInteger)timestamp
                        nonce:(NSString *)nonce
              protocolVersion:(NSInteger)protocolVersion
                   executable:(NSString *)executable
                    machoUUID:(NSString *)machoUUID
                   appVersion:(NSString *)appVersion
                     appBuild:(NSString *)appBuild
{
    NSArray *parts = @[udid ?: @"", bundleID ?: @"", dylibKey ?: @"", version ?: @"", build ?: @"", sha256.lowercaseString ?: @"",
                       [NSString stringWithFormat:@"%ld", (long)timestamp], nonce ?: @"",
                       [NSString stringWithFormat:@"%ld", (long)protocolVersion], executable ?: @"", machoUUID.uppercaseString ?: @"", appVersion ?: @"", appBuild ?: @""];
    return [parts componentsJoinedByString:@"\n"];
}

- (NSString *)hmacSHA256Hex:(NSString *)message key:(NSString *)key
{
    NSData *keyData = [key dataUsingEncoding:NSUTF8StringEncoding];
    NSData *messageData = [message dataUsingEncoding:NSUTF8StringEncoding];
    unsigned char digest[CC_SHA256_DIGEST_LENGTH];
    CCHmac(kCCHmacAlgSHA256, keyData.bytes, keyData.length, messageData.bytes, messageData.length, digest);
    NSMutableString *hex = [NSMutableString stringWithCapacity:CC_SHA256_DIGEST_LENGTH * 2];
    for (NSUInteger i = 0; i < CC_SHA256_DIGEST_LENGTH; i++) [hex appendFormat:@"%02x", digest[i]];
    return hex;
}

- (NSString *)randomHexBytes:(NSUInteger)count
{
    NSMutableData *data = [NSMutableData dataWithLength:count];
    if (SecRandomCopyBytes(kSecRandomDefault, count, data.mutableBytes) != errSecSuccess) return [NSUUID UUID].UUIDString.lowercaseString;
    const uint8_t *bytes = data.bytes;
    NSMutableString *hex = [NSMutableString stringWithCapacity:count * 2];
    for (NSUInteger i = 0; i < count; i++) [hex appendFormat:@"%02x", bytes[i]];
    return hex;
}

#pragma mark - Result/cache

- (ZONVerifyResult *)resultFromJSON:(NSDictionary *)json
{
    ZONVerifyResult *result = [ZONVerifyResult new];
    result.allowed = [json[@"ok"] boolValue];
    result.code = [json[@"code"] isKindOfClass:NSString.class] ? json[@"code"] : @"unknown";
    result.action = [json[@"action"] isKindOfClass:NSString.class] ? json[@"action"] : @"disable_feature";
    result.message = [json[@"message"] isKindOfClass:NSString.class] ? json[@"message"] : @"";
    result.token = [json[@"token"] isKindOfClass:NSString.class] ? json[@"token"] : @"";
    result.accessLevel = [json[@"access_level"] isKindOfClass:NSString.class] ? json[@"access_level"] : (result.allowed ? @"basic" : @"block");
    result.permissions = [json[@"permissions"] isKindOfClass:NSDictionary.class] ? json[@"permissions"] : @{};
    result.appIdentity = [json[@"app_identity"] isKindOfClass:NSDictionary.class] ? json[@"app_identity"] : @{};
    result.appUpdate = [json[@"app_update"] isKindOfClass:NSDictionary.class] ? json[@"app_update"] : @{ @"available": @NO };
    result.notice = [json[@"notice"] isKindOfClass:NSDictionary.class] ? json[@"notice"] : nil;
    result.protocolVersion = MAX(1, [json[@"protocol_version"] integerValue]);
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

- (NSString *)runtimeConfigService
{
    return [NSString stringWithFormat:@"com.zonoe.dylib.runtime-config.%@", self.configuration.dylibKey ?: @"unknown"];
}

- (void)storeRuntimeConfig:(NSDictionary *)config
{
    NSData *data = [NSJSONSerialization dataWithJSONObject:config options:0 error:nil];
    if (!data) return;
    NSDictionary *query = @{(__bridge id)kSecClass:(__bridge id)kSecClassGenericPassword,
                            (__bridge id)kSecAttrService:[self runtimeConfigService],
                            (__bridge id)kSecAttrAccount:ZONRuntimeConfigAccount};
    SecItemDelete((__bridge CFDictionaryRef)query);
    NSMutableDictionary *add = [query mutableCopy];
    add[(__bridge id)kSecValueData] = data;
    add[(__bridge id)kSecAttrAccessible] = (__bridge id)kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly;
    SecItemAdd((__bridge CFDictionaryRef)add, NULL);
}

- (NSDictionary *)cachedRuntimeConfigAllowStale:(BOOL)allowStale
{
    NSMutableDictionary *query = [@{(__bridge id)kSecClass:(__bridge id)kSecClassGenericPassword,
                                    (__bridge id)kSecAttrService:[self runtimeConfigService],
                                    (__bridge id)kSecAttrAccount:ZONRuntimeConfigAccount,
                                    (__bridge id)kSecReturnData:@YES,
                                    (__bridge id)kSecMatchLimit:(__bridge id)kSecMatchLimitOne} mutableCopy];
    CFTypeRef resultRef = NULL;
    OSStatus status = SecItemCopyMatching((__bridge CFDictionaryRef)query, &resultRef);
    if (status != errSecSuccess || !resultRef) return nil;
    NSData *data = CFBridgingRelease(resultRef);
    NSDictionary *config = [NSJSONSerialization JSONObjectWithData:data options:0 error:nil];
    return [self validateRuntimeConfig:config allowStale:allowStale] ? config : nil;
}

- (void)storeOfflineCacheForResult:(ZONVerifyResult *)result bundleID:(NSString *)bundleID
{
    NSDictionary *cache = @{
        @"verified_at": @([[NSDate date] timeIntervalSince1970]),
        @"offline_grace_seconds": @(MAX(0, result.offlineGraceSeconds)),
        @"token": result.token ?: @"",
        @"code": result.code ?: @"ok",
        @"message": result.message ?: @"",
        @"access_level": result.accessLevel ?: @"basic",
        @"permissions": result.permissions ?: @{},
        @"app_identity": result.appIdentity ?: @{},
        @"app_update": result.appUpdate ?: @{ @"available": @NO }
    };
    NSData *data = [NSJSONSerialization dataWithJSONObject:cache options:0 error:nil];
    if (!data) return;
    NSDictionary *query = @{(__bridge id)kSecClass:(__bridge id)kSecClassGenericPassword,
                            (__bridge id)kSecAttrService:[self cacheService],
                            (__bridge id)kSecAttrAccount:bundleID};
    SecItemDelete((__bridge CFDictionaryRef)query);
    NSMutableDictionary *add = [query mutableCopy];
    add[(__bridge id)kSecValueData] = data;
    add[(__bridge id)kSecAttrAccessible] = (__bridge id)kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly;
    SecItemAdd((__bridge CFDictionaryRef)add, NULL);
}

- (ZONVerifyResult *)validOfflineCacheForBundleID:(NSString *)bundleID
{
    NSMutableDictionary *query = [@{(__bridge id)kSecClass:(__bridge id)kSecClassGenericPassword,
                                    (__bridge id)kSecAttrService:[self cacheService],
                                    (__bridge id)kSecAttrAccount:bundleID,
                                    (__bridge id)kSecReturnData:@YES,
                                    (__bridge id)kSecMatchLimit:(__bridge id)kSecMatchLimitOne} mutableCopy];
    CFTypeRef resultRef = NULL;
    OSStatus status = SecItemCopyMatching((__bridge CFDictionaryRef)query, &resultRef);
    if (status != errSecSuccess || !resultRef) return nil;
    NSData *data = CFBridgingRelease(resultRef);
    NSDictionary *cache = [NSJSONSerialization JSONObjectWithData:data options:0 error:nil];
    if (![cache isKindOfClass:NSDictionary.class]) return nil;
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
    result.accessLevel = [cache[@"access_level"] isKindOfClass:NSString.class] ? cache[@"access_level"] : @"basic";
    result.permissions = [cache[@"permissions"] isKindOfClass:NSDictionary.class] ? cache[@"permissions"] : @{};
    result.appIdentity = [cache[@"app_identity"] isKindOfClass:NSDictionary.class] ? cache[@"app_identity"] : @{};
    result.appUpdate = [cache[@"app_update"] isKindOfClass:NSDictionary.class] ? cache[@"app_update"] : @{ @"available": @NO };
    result.offlineGraceSeconds = grace;
    result.offlineCache = YES;
    result.protocolVersion = 2;
    return result;
}

- (void)clearOfflineCacheForBundleID:(NSString *)bundleID
{
    NSDictionary *query = @{(__bridge id)kSecClass:(__bridge id)kSecClassGenericPassword,
                            (__bridge id)kSecAttrService:[self cacheService],
                            (__bridge id)kSecAttrAccount:bundleID};
    SecItemDelete((__bridge CFDictionaryRef)query);
}

@end
