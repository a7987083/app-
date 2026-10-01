#import "ZONVerifyClient.h"
#import <CommonCrypto/CommonDigest.h>
#import <Security/Security.h>
#import <dlfcn.h>
#import <mach-o/dyld.h>
#import <mach-o/loader.h>

static void ZONVerifyImageAnchor(void) {}
static NSString * const ZONRuntimeConfigAccount = @"runtime-config";
static NSTimeInterval const ZONRuntimeConfigMaxStale = 30.0 * 24.0 * 60.0 * 60.0;

static BOOL ZONReadDERLength(NSData *data, NSUInteger *offset, NSUInteger *length)
{
    if (!data || !offset || !length || *offset >= data.length) return NO;
    const uint8_t *bytes = data.bytes;
    uint8_t first = bytes[(*offset)++];
    if ((first & 0x80) == 0) {
        *length = first;
        return *offset + *length <= data.length;
    }
    NSUInteger count = first & 0x7f;
    if (count == 0 || count > sizeof(NSUInteger) || *offset + count > data.length) return NO;
    NSUInteger value = 0;
    for (NSUInteger i = 0; i < count; i++) value = (value << 8) | bytes[(*offset)++];
    *length = value;
    return *offset + *length <= data.length;
}

@implementation ZONVerifyConfiguration
- (instancetype)init
{
    self = [super init];
    if (self) {
        _dylibBuild = @"";
        _bootstrapURLs = @[];
        _serverPublicKeyPEM = @"";
        _serverKeyID = @"";
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
        _protocolVersion = 3;
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
        self.configuration.dylibVersion.length == 0 || self.configuration.serverPublicKeyPEM.length == 0 ||
        self.configuration.serverKeyID.length == 0 || executable.length == 0 || appUUID.length == 0) {
        completion([self resultAllowed:NO code:@"client_config_invalid" action:@"block" message:@"Verification client configuration is incomplete"]);
        return;
    }

    SecKeyRef privateKey = [self devicePrivateKey];
    if (!privateKey) {
        completion([self resultAllowed:NO code:@"device_key_unavailable" action:@"block" message:@"Unable to create device key"]);
        return;
    }
    NSString *publicPEM = [self publicKeyPEMForPrivateKey:privateKey];
    CFRelease(privateKey);
    if (publicPEM.length == 0) {
        completion([self resultAllowed:NO code:@"device_key_unavailable" action:@"block" message:@"Unable to export device public key"]);
        return;
    }

    NSDictionary *context = @{
        @"udid": udid,
        @"bundle_id": bundleID,
        @"dylib_key": self.configuration.dylibKey,
        @"dylib_version": self.configuration.dylibVersion,
        @"dylib_build": self.configuration.dylibBuild ?: @"",
        @"dylib_sha256": [ZONVerifyClient currentDylibSHA256] ?: @"",
        @"app_executable": executable,
        @"app_macho_uuid": appUUID,
        @"app_version": appVersion,
        @"app_build": appBuild,
        @"device_public_key": publicPEM,
    };

    __weak typeof(self) weakSelf = self;
    [self resolveVerificationEndpointsWithCompletion:^(NSArray<NSURL *> *endpoints) {
        __strong typeof(weakSelf) strongSelf = weakSelf;
        if (!strongSelf) return;
        if (endpoints.count == 0) {
            ZONVerifyResult *cached = [strongSelf validOfflineCacheForBundleID:bundleID];
            completion(cached ?: [strongSelf resultAllowed:NO code:@"network_unavailable" action:@"disable_feature" message:@"No verification endpoint available"]);
            return;
        }
        [strongSelf authenticateContext:context endpoints:endpoints index:0 bundleID:bundleID completion:completion];
    }];
}

- (void)authenticateContext:(NSDictionary *)context
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

    NSURL *verifyURL = endpoints[index];
    NSURL *challengeURL = [self challengeURLFromVerifyURL:verifyURL];
    if (!challengeURL) {
        [self authenticateContext:context endpoints:endpoints index:index + 1 bundleID:bundleID completion:completion];
        return;
    }

    NSDictionary *challengePayload = @{
        @"udid": context[@"udid"] ?: @"",
        @"dylib_key": context[@"dylib_key"] ?: @"",
        @"device_public_key": context[@"device_public_key"] ?: @"",
    };

    __weak typeof(self) weakSelf = self;
    [self postJSON:challengePayload URL:challengeURL completion:^(NSDictionary * _Nullable challengeJSON, NSError * _Nullable error) {
        __strong typeof(weakSelf) strongSelf = weakSelf;
        if (!strongSelf) return;
        if (error || ![challengeJSON isKindOfClass:NSDictionary.class]) {
            [strongSelf authenticateContext:context endpoints:endpoints index:index + 1 bundleID:bundleID completion:completion];
            return;
        }
        if (![challengeJSON[@"ok"] boolValue]) {
            completion([strongSelf resultAllowed:NO
                                            code:[challengeJSON[@"code"] isKindOfClass:NSString.class] ? challengeJSON[@"code"] : @"challenge_unavailable"
                                          action:@"block"
                                         message:[challengeJSON[@"message"] isKindOfClass:NSString.class] ? challengeJSON[@"message"] : @"Challenge unavailable"]);
            return;
        }

        NSString *challengeID = [challengeJSON[@"challenge_id"] isKindOfClass:NSString.class] ? challengeJSON[@"challenge_id"] : @"";
        NSString *challenge = [challengeJSON[@"challenge"] isKindOfClass:NSString.class] ? challengeJSON[@"challenge"] : @"";
        if (challengeID.length == 0 || challenge.length == 0) {
            completion([strongSelf resultAllowed:NO code:@"challenge_invalid" action:@"block" message:@"Invalid challenge response"]);
            return;
        }

        NSMutableDictionary *verifyPayload = [context mutableCopy];
        verifyPayload[@"protocol_version"] = @3;
        verifyPayload[@"challenge_id"] = challengeID;
        verifyPayload[@"challenge"] = challenge;

        SecKeyRef privateKey = [strongSelf devicePrivateKey];
        if (!privateKey) {
            completion([strongSelf resultAllowed:NO code:@"device_key_unavailable" action:@"block" message:@"Device key unavailable"]);
            return;
        }
        NSData *signature = [strongSelf signatureForString:[strongSelf canonicalProof:verifyPayload] privateKey:privateKey];
        CFRelease(privateKey);
        if (!signature) {
            completion([strongSelf resultAllowed:NO code:@"device_signature_failed" action:@"block" message:@"Unable to sign challenge"]);
            return;
        }
        verifyPayload[@"device_signature"] = [signature base64EncodedStringWithOptions:0];

        if ([challengeJSON[@"enrollment_required"] boolValue]) {
            NSString *licenseCode = strongSelf.configuration.licenseCodeProvider ? strongSelf.configuration.licenseCodeProvider() : @"";
            if (licenseCode.length == 0) {
                completion([strongSelf resultAllowed:NO code:@"license_required" action:@"block" message:@"First device enrollment requires the current license code"]);
                return;
            }
            verifyPayload[@"license_code"] = licenseCode;
        }

        [strongSelf postJSON:verifyPayload URL:verifyURL completion:^(NSDictionary * _Nullable verifyJSON, NSError * _Nullable verifyError) {
            if (verifyError || ![verifyJSON isKindOfClass:NSDictionary.class]) {
                [strongSelf authenticateContext:context endpoints:endpoints index:index + 1 bundleID:bundleID completion:completion];
                return;
            }
            ZONVerifyResult *result = [strongSelf resultFromJSON:verifyJSON];
            if (result.isAllowed) {
                [strongSelf storeOfflineCacheForResult:result bundleID:bundleID];
            } else {
                [strongSelf clearOfflineCacheForBundleID:bundleID];
            }
            completion(result);
        }];
    }];
}

- (void)postJSON:(NSDictionary *)json
              URL:(NSURL *)url
       completion:(void (^)(NSDictionary * _Nullable json, NSError * _Nullable error))completion
{
    NSError *encodeError = nil;
    NSData *body = [NSJSONSerialization dataWithJSONObject:json options:0 error:&encodeError];
    if (!body) {
        completion(nil, encodeError);
        return;
    }
    NSMutableURLRequest *request = [NSMutableURLRequest requestWithURL:url];
    request.HTTPMethod = @"POST";
    request.HTTPBody = body;
    [request setValue:@"application/json" forHTTPHeaderField:@"Content-Type"];
    [request setValue:@"application/json" forHTTPHeaderField:@"Accept"];
    NSURLSessionDataTask *task = [self.session dataTaskWithRequest:request completionHandler:^(NSData * _Nullable data, NSURLResponse * _Nullable response, NSError * _Nullable error) {
        NSHTTPURLResponse *http = [response isKindOfClass:NSHTTPURLResponse.class] ? (NSHTTPURLResponse *)response : nil;
        if (error || http.statusCode >= 500 || data.length == 0) {
            NSError *finalError = error ?: [NSError errorWithDomain:@"ZONVerify" code:http.statusCode ?: 1 userInfo:nil];
            completion(nil, finalError);
            return;
        }
        NSError *decodeError = nil;
        NSDictionary *decoded = [NSJSONSerialization JSONObjectWithData:data options:0 error:&decodeError];
        completion([decoded isKindOfClass:NSDictionary.class] ? decoded : nil,
                   [decoded isKindOfClass:NSDictionary.class] ? nil : (decodeError ?: [NSError errorWithDomain:@"ZONVerify" code:2 userInfo:nil]));
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
    NSString *algorithm = [config[@"signature_alg"] isKindOfClass:NSString.class] ? config[@"signature_alg"] : nil;
    NSString *keyID = [config[@"key_id"] isKindOfClass:NSString.class] ? config[@"key_id"] : nil;
    NSInteger version = [config[@"config_version"] integerValue];
    NSTimeInterval expires = [config[@"expires_at"] doubleValue];
    if (!apis || !bootstraps || path.length == 0 || signature.length == 0 || version < 3 || expires <= 0) return NO;
    if (![algorithm isEqualToString:@"rsa-2048-sha256"] || keyID.length == 0) return NO;
    if (self.configuration.serverKeyID.length > 0 && ![keyID isEqualToString:self.configuration.serverKeyID]) return NO;

    NSTimeInterval now = [[NSDate date] timeIntervalSince1970];
    if (!allowStale && expires < now) return NO;
    if (allowStale && expires < now) {
        NSTimeInterval received = [config[@"received_at"] doubleValue];
        if (received <= 0 || now - received > ZONRuntimeConfigMaxStale) return NO;
    }

    NSString *canonical = [self canonicalRuntimeConfigVersion:version apiEndpoints:apis bootstrapURLs:bootstraps verifyPath:path expiresAt:(NSInteger)expires];
    NSData *signatureData = [[NSData alloc] initWithBase64EncodedString:signature options:0];
    SecKeyRef serverKey = [self serverPublicKey];
    if (!signatureData || !serverKey) {
        if (serverKey) CFRelease(serverKey);
        return NO;
    }
    NSData *message = [canonical dataUsingEncoding:NSUTF8StringEncoding];
    BOOL ok = SecKeyVerifySignature(serverKey,
                                    kSecKeyAlgorithmRSASignatureMessagePKCS1v15SHA256,
                                    (__bridge CFDataRef)message,
                                    (__bridge CFDataRef)signatureData,
                                    NULL);
    CFRelease(serverKey);
    return ok;
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

- (NSURL *)challengeURLFromVerifyURL:(NSURL *)verifyURL
{
    if (!verifyURL) return nil;
    NSString *absolute = verifyURL.absoluteString ?: @"";
    NSRange range = [absolute rangeOfString:@"/verify" options:NSBackwardsSearch];
    if (range.location == NSNotFound) return nil;
    return [NSURL URLWithString:[absolute stringByReplacingCharactersInRange:range withString:@"/challenge"]];
}

#pragma mark - Device and server keys

- (NSString *)canonicalProof:(NSDictionary *)payload
{
    return [@[ @"zonoe-dylib-auth-v3",
               payload[@"challenge_id"] ?: @"",
               payload[@"challenge"] ?: @"",
               payload[@"udid"] ?: @"",
               payload[@"bundle_id"] ?: @"",
               payload[@"dylib_key"] ?: @"",
               payload[@"dylib_version"] ?: @"",
               payload[@"dylib_build"] ?: @"",
               [payload[@"dylib_sha256"] lowercaseString] ?: @"",
               payload[@"app_executable"] ?: @"",
               [payload[@"app_macho_uuid"] uppercaseString] ?: @"",
               payload[@"app_version"] ?: @"",
               payload[@"app_build"] ?: @"" ] componentsJoinedByString:@"\n"];
}

- (SecKeyRef)devicePrivateKey
{
    NSData *tag = [[NSString stringWithFormat:@"xyz.zonoe.dylib-auth.%@", self.configuration.dylibKey ?: @"unknown"] dataUsingEncoding:NSUTF8StringEncoding];
    NSDictionary *query = @{
        (__bridge id)kSecClass:(__bridge id)kSecClassKey,
        (__bridge id)kSecAttrApplicationTag:tag,
        (__bridge id)kSecAttrKeyType:(__bridge id)kSecAttrKeyTypeECSECPrimeRandom,
        (__bridge id)kSecReturnRef:@YES,
    };
    SecKeyRef key = NULL;
    OSStatus status = SecItemCopyMatching((__bridge CFDictionaryRef)query, (CFTypeRef *)&key);
    if (status == errSecSuccess && key) return key;

    NSDictionary *privateAttrs = @{
        (__bridge id)kSecAttrIsPermanent:@YES,
        (__bridge id)kSecAttrApplicationTag:tag,
        (__bridge id)kSecAttrAccessible:(__bridge id)kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly,
    };
    NSDictionary *attrs = @{
        (__bridge id)kSecAttrKeyType:(__bridge id)kSecAttrKeyTypeECSECPrimeRandom,
        (__bridge id)kSecAttrKeySizeInBits:@256,
        (__bridge id)kSecPrivateKeyAttrs:privateAttrs,
    };
    return SecKeyCreateRandomKey((__bridge CFDictionaryRef)attrs, NULL);
}

- (NSString *)publicKeyPEMForPrivateKey:(SecKeyRef)privateKey
{
    SecKeyRef publicKey = SecKeyCopyPublicKey(privateKey);
    if (!publicKey) return @"";
    CFErrorRef error = NULL;
    CFDataRef rawRef = SecKeyCopyExternalRepresentation(publicKey, &error);
    CFRelease(publicKey);
    if (!rawRef) {
        if (error) CFRelease(error);
        return @"";
    }
    NSData *raw = CFBridgingRelease(rawRef);
    const unsigned char prefixBytes[] = {
        0x30,0x59,0x30,0x13,0x06,0x07,0x2A,0x86,0x48,0xCE,0x3D,0x02,0x01,
        0x06,0x08,0x2A,0x86,0x48,0xCE,0x3D,0x03,0x01,0x07,0x03,0x42,0x00
    };
    NSMutableData *spki = [NSMutableData dataWithBytes:prefixBytes length:sizeof(prefixBytes)];
    [spki appendData:raw];
    NSString *base64 = [spki base64EncodedStringWithOptions:0];
    NSMutableString *lines = [NSMutableString string];
    for (NSUInteger i = 0; i < base64.length; i += 64) {
        [lines appendFormat:@"%@\n", [base64 substringWithRange:NSMakeRange(i, MIN((NSUInteger)64, base64.length - i))]];
    }
    return [NSString stringWithFormat:@"-----BEGIN PUBLIC KEY-----\n%@-----END PUBLIC KEY-----\n", lines];
}

- (NSData *)signatureForString:(NSString *)string privateKey:(SecKeyRef)privateKey
{
    NSData *data = [string dataUsingEncoding:NSUTF8StringEncoding];
    CFErrorRef error = NULL;
    CFDataRef signature = SecKeyCreateSignature(privateKey,
                                                kSecKeyAlgorithmECDSASignatureMessageX962SHA256,
                                                (__bridge CFDataRef)data,
                                                &error);
    if (!signature) {
        if (error) CFRelease(error);
        return nil;
    }
    return CFBridgingRelease(signature);
}

- (NSData *)rsaPKCS1FromSPKI:(NSData *)spki
{
    if (spki.length < 16) return nil;
    const uint8_t *bytes = spki.bytes;
    NSUInteger offset = 0, length = 0;
    if (bytes[offset++] != 0x30 || !ZONReadDERLength(spki, &offset, &length)) return nil;
    if (offset >= spki.length || bytes[offset++] != 0x30 || !ZONReadDERLength(spki, &offset, &length) || offset + length > spki.length) return nil;
    offset += length;
    if (offset >= spki.length || bytes[offset++] != 0x03 || !ZONReadDERLength(spki, &offset, &length) || length < 2 || offset + length > spki.length) return nil;
    if (bytes[offset] != 0x00) return nil;
    offset++;
    length--;
    if (offset + length > spki.length) return nil;
    return [spki subdataWithRange:NSMakeRange(offset, length)];
}

- (SecKeyRef)serverPublicKey
{
    NSString *pem = self.configuration.serverPublicKeyPEM ?: @"";
    NSString *body = [pem stringByReplacingOccurrencesOfString:@"-----BEGIN PUBLIC KEY-----" withString:@""];
    body = [body stringByReplacingOccurrencesOfString:@"-----END PUBLIC KEY-----" withString:@""];
    body = [[body componentsSeparatedByCharactersInSet:[NSCharacterSet whitespaceAndNewlineCharacterSet]] componentsJoinedByString:@""];
    NSData *spki = [[NSData alloc] initWithBase64EncodedString:body options:0];
    NSData *pkcs1 = [self rsaPKCS1FromSPKI:spki];
    if (!pkcs1) return NULL;
    NSDictionary *attrs = @{
        (__bridge id)kSecAttrKeyType:(__bridge id)kSecAttrKeyTypeRSA,
        (__bridge id)kSecAttrKeyClass:(__bridge id)kSecAttrKeyClassPublic,
        (__bridge id)kSecAttrKeySizeInBits:@2048,
    };
    return SecKeyCreateWithData((__bridge CFDataRef)pkcs1, (__bridge CFDictionaryRef)attrs, NULL);
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
    result.protocolVersion = MAX(3, [json[@"protocol_version"] integerValue]);
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

    NSString *accessLevel = [cache[@"access_level"] isKindOfClass:NSString.class] ? cache[@"access_level"] : @"basic";
    NSDictionary *cachedIdentity = [cache[@"app_identity"] isKindOfClass:NSDictionary.class] ? cache[@"app_identity"] : @{};
    if ([accessLevel isEqualToString:@"app_plus"]) {
        NSString *currentExecutable = [NSBundle.mainBundle objectForInfoDictionaryKey:@"CFBundleExecutable"] ?: @"";
        NSString *currentUUID = [ZONVerifyClient currentAppMachOUUID] ?: @"";
        NSString *cachedBundleID = [cachedIdentity[@"bundle_id"] isKindOfClass:NSString.class] ? cachedIdentity[@"bundle_id"] : @"";
        NSString *cachedExecutable = [cachedIdentity[@"executable"] isKindOfClass:NSString.class] ? cachedIdentity[@"executable"] : @"";
        NSString *cachedUUID = [cachedIdentity[@"macho_uuid"] isKindOfClass:NSString.class] ? cachedIdentity[@"macho_uuid"] : @"";
        BOOL identityMatches = [cachedIdentity[@"resolved"] boolValue] &&
            cachedBundleID.length > 0 && [cachedBundleID isEqualToString:bundleID] &&
            cachedExecutable.length > 0 && [cachedExecutable isEqualToString:currentExecutable] &&
            cachedUUID.length > 0 && [cachedUUID caseInsensitiveCompare:currentUUID] == NSOrderedSame;
        if (!identityMatches) {
            [self clearOfflineCacheForBundleID:bundleID];
            return nil;
        }
    }

    ZONVerifyResult *result = [ZONVerifyResult new];
    result.allowed = YES;
    result.code = @"offline_grace";
    result.action = @"allow";
    result.message = [cache[@"message"] isKindOfClass:NSString.class] ? cache[@"message"] : @"Offline grace active";
    result.token = [cache[@"token"] isKindOfClass:NSString.class] ? cache[@"token"] : @"";
    result.accessLevel = accessLevel;
    result.permissions = [cache[@"permissions"] isKindOfClass:NSDictionary.class] ? cache[@"permissions"] : @{};
    result.appIdentity = cachedIdentity;
    result.appUpdate = [cache[@"app_update"] isKindOfClass:NSDictionary.class] ? cache[@"app_update"] : @{ @"available": @NO };
    result.offlineGraceSeconds = grace;
    result.offlineCache = YES;
    result.protocolVersion = 3;
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
