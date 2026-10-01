#import "ZONVerifyClient.h"
#import <CommonCrypto/CommonDigest.h>
#import <Security/Security.h>
#import <dlfcn.h>
#import <mach-o/dyld.h>
#import <mach-o/loader.h>

static void ZONVerifyImageAnchor(void) {}

static BOOL ZONReadDERLength(NSData *data, NSUInteger *offset, NSUInteger *length)
{
    if (!data || !offset || !length || *offset >= data.length) return NO;
    const uint8_t *bytes = data.bytes;
    uint8_t first = bytes[(*offset)++];
    if ((first & 0x80) == 0) { *length = first; return *offset + *length <= data.length; }
    NSUInteger count = first & 0x7f;
    if (count == 0 || count > sizeof(NSUInteger) || *offset + count > data.length) return NO;
    NSUInteger value = 0;
    for (NSUInteger i = 0; i < count; i++) value = (value << 8) | bytes[(*offset)++];
    *length = value;
    return *offset + *length <= data.length;
}

@implementation ZONVerifyConfiguration
- (instancetype)init { self=[super init]; if(self){ _dylibBuild=@""; _bootstrapURLs=@[]; _serverPublicKeyPEM=@""; _serverKeyID=@""; _requestTimeout=10.0; } return self; }
@end

@implementation ZONVerifyResult
- (instancetype)init { self=[super init]; if(self){ _code=@"unknown"; _action=@"disable_feature"; _message=@""; _token=@""; _accessLevel=@"block"; _permissions=@{}; _appIdentity=@{}; _appUpdate=@{ @"available":@NO }; _protocolVersion=3; } return self; }
@end

@interface ZONVerifyClient ()
@property(nonatomic,strong) ZONVerifyConfiguration *configuration;
@property(nonatomic,strong) NSURLSession *session;
@end

@implementation ZONVerifyClient

- (instancetype)initWithConfiguration:(ZONVerifyConfiguration *)configuration
{
    NSParameterAssert(configuration);
    self=[super init];
    if(self){
        _configuration=configuration;
        NSURLSessionConfiguration *cfg=[NSURLSessionConfiguration ephemeralSessionConfiguration];
        cfg.timeoutIntervalForRequest=MAX(3.0,configuration.requestTimeout);
        cfg.timeoutIntervalForResource=MAX(5.0,configuration.requestTimeout+5.0);
        _session=[NSURLSession sessionWithConfiguration:cfg];
    }
    return self;
}

- (void)verifyWithCompletion:(void (^)(ZONVerifyResult *result))completion
{
    if(!completion) return;
    NSString *udid=self.configuration.udidProvider?self.configuration.udidProvider():@"";
    NSString *bundleID=NSBundle.mainBundle.bundleIdentifier?:@"";
    NSString *executable=[NSBundle.mainBundle objectForInfoDictionaryKey:@"CFBundleExecutable"]?:@"";
    NSString *appVersion=[NSBundle.mainBundle objectForInfoDictionaryKey:@"CFBundleShortVersionString"]?:@"";
    NSString *appBuild=[NSBundle.mainBundle objectForInfoDictionaryKey:@"CFBundleVersion"]?:@"";
    NSString *appUUID=[ZONVerifyClient currentAppMachOUUID]?:@"";
    if(udid.length==0||bundleID.length==0||self.configuration.dylibKey.length==0||self.configuration.dylibVersion.length==0||self.configuration.serverPublicKeyPEM.length==0||executable.length==0||appUUID.length==0){ completion([self resultAllowed:NO code:@"client_config_invalid" action:@"block" message:@"Verification client configuration is incomplete"]); return; }

    SecKeyRef privateKey=[self devicePrivateKey];
    if(!privateKey){ completion([self resultAllowed:NO code:@"device_key_unavailable" action:@"block" message:@"Unable to create device key"]); return; }
    NSString *publicPEM=[self publicKeyPEMForPrivateKey:privateKey];
    if(publicPEM.length==0){ CFRelease(privateKey); completion([self resultAllowed:NO code:@"device_key_unavailable" action:@"block" message:@"Unable to export device public key"]); return; }

    __weak typeof(self) weakSelf=self;
    [self resolveEndpoints:^(NSURL *challengeURL, NSURL *verifyURL){
        __strong typeof(weakSelf) self=weakSelf;
        if(!self){ CFRelease(privateKey); return; }
        if(!challengeURL||!verifyURL){
            CFRelease(privateKey);
            ZONVerifyResult *cached=[self cachedResultForBundleID:bundleID];
            completion(cached?:[self resultAllowed:NO code:@"network_unavailable" action:@"disable_feature" message:@"No verification endpoint available"]);
            return;
        }
        NSDictionary *challengeRequest=@{ @"udid":udid,@"dylib_key":self.configuration.dylibKey,@"device_public_key":publicPEM };
        [self postJSON:challengeRequest URL:challengeURL completion:^(NSDictionary *json,NSError *error){
            if(error||![json[@"ok"] boolValue]){
                CFRelease(privateKey);
                ZONVerifyResult *cached=[self cachedResultForBundleID:bundleID];
                completion(cached?:[self resultAllowed:NO code:json[@"code"]?:@"challenge_unavailable" action:@"disable_feature" message:json[@"message"]?:error.localizedDescription?:@"Challenge unavailable"]);
                return;
            }
            NSString *challengeID=[json[@"challenge_id"] isKindOfClass:NSString.class]?json[@"challenge_id"]:@"";
            NSString *challenge=[json[@"challenge"] isKindOfClass:NSString.class]?json[@"challenge"]:@"";
            NSString *sha256=[ZONVerifyClient currentDylibSHA256]?:@"";
            NSDictionary *base=@{
                @"protocol_version":@3,@"udid":udid,@"bundle_id":bundleID,@"dylib_key":self.configuration.dylibKey,
                @"dylib_version":self.configuration.dylibVersion,@"dylib_build":self.configuration.dylibBuild?:@"",@"dylib_sha256":sha256,
                @"app_executable":executable,@"app_macho_uuid":appUUID,@"app_version":appVersion,@"app_build":appBuild,
                @"challenge_id":challengeID,@"challenge":challenge,@"device_public_key":publicPEM
            };
            NSString *canonical=[self canonicalProof:base];
            NSData *signature=[self signatureForString:canonical privateKey:privateKey];
            CFRelease(privateKey);
            if(!signature){ completion([self resultAllowed:NO code:@"device_signature_failed" action:@"block" message:@"Unable to sign challenge"]); return; }
            NSMutableDictionary *verify=[base mutableCopy];
            verify[@"device_signature"]=[signature base64EncodedStringWithOptions:0];
            if([json[@"enrollment_required"] boolValue]){
                NSString *license=self.configuration.licenseCodeProvider?self.configuration.licenseCodeProvider():@"";
                if(license.length==0){ completion([self resultAllowed:NO code:@"license_required" action:@"block" message:@"First device enrollment requires the current license code"]); return; }
                verify[@"license_code"]=license;
            }
            [self postJSON:verify URL:verifyURL completion:^(NSDictionary *response,NSError *verifyError){
                if(verifyError||![response isKindOfClass:NSDictionary.class]){
                    ZONVerifyResult *cached=[self cachedResultForBundleID:bundleID];
                    completion(cached?:[self resultAllowed:NO code:@"network_unavailable" action:@"disable_feature" message:verifyError.localizedDescription?:@"Verification service unavailable"]);
                    return;
                }
                ZONVerifyResult *result=[self resultFromJSON:response];
                if(result.isAllowed) [self storeCachedResult:response bundleID:bundleID]; else [self clearCachedResultForBundleID:bundleID];
                completion(result);
            }];
        }];
    }];
}

- (void)resolveEndpoints:(void(^)(NSURL *challengeURL,NSURL *verifyURL))completion
{
    if(self.configuration.bootstrapURLs.count==0){ completion([self challengeURLFromVerifyURL:self.configuration.endpointURL],self.configuration.endpointURL); return; }
    [self fetchBootstrapAtIndex:0 completion:^(NSDictionary *config){
        if(config){
            NSArray *apis=[config[@"api_endpoints"] isKindOfClass:NSArray.class]?config[@"api_endpoints"]:@[];
            NSString *verifyPath=[config[@"verify_path"] isKindOfClass:NSString.class]?config[@"verify_path"]:@"/index/dylib_verify/verify";
            NSString *challengePath=[config[@"challenge_path"] isKindOfClass:NSString.class]?config[@"challenge_path"]:@"/index/dylib_verify/challenge";
            if(apis.count>0&&[apis[0] isKindOfClass:NSString.class]){
                NSString *base=[apis[0] stringByTrimmingCharactersInSet:NSCharacterSet.whitespaceAndNewlineCharacterSet];
                while([base hasSuffix:@"/"]) base=[base substringToIndex:base.length-1];
                completion([NSURL URLWithString:[base stringByAppendingString:[challengePath hasPrefix:@"/"]?challengePath:[@"/" stringByAppendingString:challengePath]]],[NSURL URLWithString:[base stringByAppendingString:[verifyPath hasPrefix:@"/"]?verifyPath:[@"/" stringByAppendingString:verifyPath]]]);
                return;
            }
        }
        completion([self challengeURLFromVerifyURL:self.configuration.endpointURL],self.configuration.endpointURL);
    }];
}

- (void)fetchBootstrapAtIndex:(NSUInteger)index completion:(void(^)(NSDictionary *config))completion
{
    if(index>=self.configuration.bootstrapURLs.count){ completion(nil); return; }
    NSURLComponents *components=[NSURLComponents componentsWithURL:self.configuration.bootstrapURLs[index] resolvingAgainstBaseURL:NO];
    NSMutableArray *items=[NSMutableArray arrayWithArray:components.queryItems?:@[]];
    [items addObject:[NSURLQueryItem queryItemWithName:@"dylib_key" value:self.configuration.dylibKey]];
    components.queryItems=items;
    NSURL *url=components.URL;
    if(!url){ [self fetchBootstrapAtIndex:index+1 completion:completion]; return; }
    NSURLSessionDataTask *task=[self.session dataTaskWithURL:url completionHandler:^(NSData *data,NSURLResponse *response,NSError *error){
        NSDictionary *json=data?[NSJSONSerialization JSONObjectWithData:data options:0 error:nil]:nil;
        if(error||![self validateRuntimeConfig:json]){ [self fetchBootstrapAtIndex:index+1 completion:completion]; return; }
        completion(json);
    }];
    [task resume];
}

- (BOOL)validateRuntimeConfig:(NSDictionary *)config
{
    if(![config isKindOfClass:NSDictionary.class]||![config[@"ok"] boolValue]) return NO;
    if([config[@"protocol_version"] integerValue]!=3) return NO;
    if(![[config[@"signature_alg"] description] isEqualToString:@"rsa-2048-sha256"]) return NO;
    NSString *keyID=[config[@"key_id"] isKindOfClass:NSString.class]?config[@"key_id"]:@"";
    if(self.configuration.serverKeyID.length&&![keyID isEqualToString:self.configuration.serverKeyID]) return NO;
    NSArray *apis=[config[@"api_endpoints"] isKindOfClass:NSArray.class]?config[@"api_endpoints"]:nil;
    NSArray *boots=[config[@"bootstrap_urls"] isKindOfClass:NSArray.class]?config[@"bootstrap_urls"]:nil;
    NSString *challengePath=[config[@"challenge_path"] isKindOfClass:NSString.class]?config[@"challenge_path"]:nil;
    NSString *verifyPath=[config[@"verify_path"] isKindOfClass:NSString.class]?config[@"verify_path"]:nil;
    NSInteger version=[config[@"config_version"] integerValue];
    NSInteger expires=[config[@"expires_at"] integerValue];
    NSString *signature=[config[@"signature"] isKindOfClass:NSString.class]?config[@"signature"]:@"";
    if(!apis||!boots||challengePath.length==0||verifyPath.length==0||version<3||expires<(NSInteger)NSDate.date.timeIntervalSince1970||signature.length==0) return NO;
    NSString *canonical=[@[ @"zonoe-runtime-config-v3",[NSString stringWithFormat:@"%ld",(long)version],[apis componentsJoinedByString:@","],[boots componentsJoinedByString:@","],challengePath,verifyPath,[NSString stringWithFormat:@"%ld",(long)expires] ] componentsJoinedByString:@"\n"];
    NSData *sig=[[NSData alloc] initWithBase64EncodedString:signature options:0];
    SecKeyRef serverKey=[self serverPublicKey];
    if(!sig||!serverKey) return NO;
    NSData *message=[canonical dataUsingEncoding:NSUTF8StringEncoding];
    BOOL ok=SecKeyVerifySignature(serverKey,kSecKeyAlgorithmRSASignatureMessagePKCS1v15SHA256,(__bridge CFDataRef)message,(__bridge CFDataRef)sig,NULL);
    CFRelease(serverKey);
    return ok;
}

- (NSURL *)challengeURLFromVerifyURL:(NSURL *)verifyURL
{
    if(!verifyURL) return nil;
    NSString *absolute=verifyURL.absoluteString;
    NSRange range=[absolute rangeOfString:@"/verify" options:NSBackwardsSearch];
    if(range.location==NSNotFound) return nil;
    return [NSURL URLWithString:[absolute stringByReplacingCharactersInRange:range withString:@"/challenge"]];
}

- (void)postJSON:(NSDictionary *)json URL:(NSURL *)url completion:(void(^)(NSDictionary *json,NSError *error))completion
{
    NSError *encodeError=nil; NSData *body=[NSJSONSerialization dataWithJSONObject:json options:0 error:&encodeError];
    if(!body){ completion(nil,encodeError); return; }
    NSMutableURLRequest *request=[NSMutableURLRequest requestWithURL:url]; request.HTTPMethod=@"POST"; request.HTTPBody=body;
    [request setValue:@"application/json" forHTTPHeaderField:@"Content-Type"]; [request setValue:@"application/json" forHTTPHeaderField:@"Accept"];
    NSURLSessionDataTask *task=[self.session dataTaskWithRequest:request completionHandler:^(NSData *data,NSURLResponse *response,NSError *error){
        if(error||data.length==0){ completion(nil,error?:[NSError errorWithDomain:@"ZONVerify" code:1 userInfo:nil]); return; }
        NSDictionary *decoded=[NSJSONSerialization JSONObjectWithData:data options:0 error:nil];
        completion([decoded isKindOfClass:NSDictionary.class]?decoded:nil,[decoded isKindOfClass:NSDictionary.class]?nil:[NSError errorWithDomain:@"ZONVerify" code:2 userInfo:nil]);
    }]; [task resume];
}

- (NSString *)canonicalProof:(NSDictionary *)p
{
    return [@[ @"zonoe-dylib-auth-v3",p[@"challenge_id"]?:@"",p[@"challenge"]?:@"",p[@"udid"]?:@"",p[@"bundle_id"]?:@"",p[@"dylib_key"]?:@"",p[@"dylib_version"]?:@"",p[@"dylib_build"]?:@"",[p[@"dylib_sha256"] lowercaseString]?:@"",p[@"app_executable"]?:@"",[p[@"app_macho_uuid"] uppercaseString]?:@"",p[@"app_version"]?:@"",p[@"app_build"]?:@"" ] componentsJoinedByString:@"\n"];
}

- (SecKeyRef)devicePrivateKey
{
    NSData *tag=[[NSString stringWithFormat:@"xyz.zonoe.dylib-auth.%@",self.configuration.dylibKey] dataUsingEncoding:NSUTF8StringEncoding];
    NSDictionary *query=@{(__bridge id)kSecClass:(__bridge id)kSecClassKey,(__bridge id)kSecAttrApplicationTag:tag,(__bridge id)kSecAttrKeyType:(__bridge id)kSecAttrKeyTypeECSECPrimeRandom,(__bridge id)kSecReturnRef:@YES};
    SecKeyRef key=NULL; OSStatus status=SecItemCopyMatching((__bridge CFDictionaryRef)query,(CFTypeRef *)&key);
    if(status==errSecSuccess&&key) return key;
    NSDictionary *privateAttrs=@{(__bridge id)kSecAttrIsPermanent:@YES,(__bridge id)kSecAttrApplicationTag:tag};
    NSDictionary *attrs=@{(__bridge id)kSecAttrKeyType:(__bridge id)kSecAttrKeyTypeECSECPrimeRandom,(__bridge id)kSecAttrKeySizeInBits:@256,(__bridge id)kSecPrivateKeyAttrs:privateAttrs};
    return SecKeyCreateRandomKey((__bridge CFDictionaryRef)attrs,NULL);
}

- (NSString *)publicKeyPEMForPrivateKey:(SecKeyRef)privateKey
{
    SecKeyRef pub=SecKeyCopyPublicKey(privateKey); if(!pub) return @"";
    CFErrorRef error=NULL; CFDataRef rawRef=SecKeyCopyExternalRepresentation(pub,&error); CFRelease(pub);
    if(!rawRef){ if(error) CFRelease(error); return @""; }
    NSData *raw=(__bridge_transfer NSData *)rawRef;
    const unsigned char prefixBytes[]={0x30,0x59,0x30,0x13,0x06,0x07,0x2A,0x86,0x48,0xCE,0x3D,0x02,0x01,0x06,0x08,0x2A,0x86,0x48,0xCE,0x3D,0x03,0x01,0x07,0x03,0x42,0x00};
    NSMutableData *spki=[NSMutableData dataWithBytes:prefixBytes length:sizeof(prefixBytes)]; [spki appendData:raw];
    NSString *b64=[spki base64EncodedStringWithOptions:0]; NSMutableString *lines=[NSMutableString string];
    for(NSUInteger i=0;i<b64.length;i+=64) [lines appendFormat:@"%@\n",[b64 substringWithRange:NSMakeRange(i,MIN((NSUInteger)64,b64.length-i))]];
    return [NSString stringWithFormat:@"-----BEGIN PUBLIC KEY-----\n%@-----END PUBLIC KEY-----\n",lines];
}

- (NSData *)signatureForString:(NSString *)string privateKey:(SecKeyRef)privateKey
{
    NSData *data=[string dataUsingEncoding:NSUTF8StringEncoding]; CFErrorRef error=NULL;
    CFDataRef sig=SecKeyCreateSignature(privateKey,kSecKeyAlgorithmECDSASignatureMessageX962SHA256,(__bridge CFDataRef)data,&error);
    if(!sig){ if(error) CFRelease(error); return nil; }
    return CFBridgingRelease(sig);
}

- (NSData *)rsaPKCS1FromSPKI:(NSData *)spki
{
    if(spki.length<16) return nil;
    const uint8_t *bytes=spki.bytes; NSUInteger offset=0,length=0;
    if(bytes[offset++]!=0x30||!ZONReadDERLength(spki,&offset,&length)) return nil;
    if(offset>=spki.length||bytes[offset++]!=0x30||!ZONReadDERLength(spki,&offset,&length)||offset+length>spki.length) return nil;
    offset+=length;
    if(offset>=spki.length||bytes[offset++]!=0x03||!ZONReadDERLength(spki,&offset,&length)||length<2||offset+length>spki.length) return nil;
    if(bytes[offset]!=0x00) return nil;
    offset++; length--;
    if(offset+length>spki.length) return nil;
    return [spki subdataWithRange:NSMakeRange(offset,length)];
}

- (SecKeyRef)serverPublicKey
{
    NSString *pem=self.configuration.serverPublicKeyPEM?:@"";
    NSString *body=[pem stringByReplacingOccurrencesOfString:@"-----BEGIN PUBLIC KEY-----" withString:@""];
    body=[body stringByReplacingOccurrencesOfString:@"-----END PUBLIC KEY-----" withString:@""];
    body=[[body componentsSeparatedByCharactersInSet:NSCharacterSet.whitespaceAndNewlineCharacterSet] componentsJoinedByString:@""];
    NSData *spki=[[NSData alloc] initWithBase64EncodedString:body options:0];
    NSData *pkcs1=[self rsaPKCS1FromSPKI:spki];
    if(!pkcs1) return NULL;
    NSDictionary *attrs=@{(__bridge id)kSecAttrKeyType:(__bridge id)kSecAttrKeyTypeRSA,(__bridge id)kSecAttrKeyClass:(__bridge id)kSecAttrKeyClassPublic,(__bridge id)kSecAttrKeySizeInBits:@2048};
    return SecKeyCreateWithData((__bridge CFDataRef)pkcs1,(__bridge CFDictionaryRef)attrs,NULL);
}

- (ZONVerifyResult *)resultFromJSON:(NSDictionary *)json
{
    ZONVerifyResult *r=[ZONVerifyResult new]; r.allowed=[json[@"ok"] boolValue];
    r.code=[json[@"code"] isKindOfClass:NSString.class]?json[@"code"]:@"unknown";
    r.action=[json[@"action"] isKindOfClass:NSString.class]?json[@"action"]:@"disable_feature";
    r.message=[json[@"message"] isKindOfClass:NSString.class]?json[@"message"]:@"";
    r.token=[json[@"token"] isKindOfClass:NSString.class]?json[@"token"]:@"";
    r.accessLevel=[json[@"access_level"] isKindOfClass:NSString.class]?json[@"access_level"]:@"block";
    r.permissions=[json[@"permissions"] isKindOfClass:NSDictionary.class]?json[@"permissions"]:@{};
    r.appIdentity=[json[@"app_identity"] isKindOfClass:NSDictionary.class]?json[@"app_identity"]:@{};
    r.appUpdate=[json[@"app_update"] isKindOfClass:NSDictionary.class]?json[@"app_update"]:@{ @"available":@NO };
    r.notice=[json[@"notice"] isKindOfClass:NSDictionary.class]?json[@"notice"]:nil;
    r.protocolVersion=[json[@"protocol_version"] integerValue]?:3; r.offlineGraceSeconds=[json[@"offline_grace_seconds"] doubleValue]; r.serverTime=[json[@"server_time"] doubleValue];
    return r;
}

- (ZONVerifyResult *)resultAllowed:(BOOL)allowed code:(NSString *)code action:(NSString *)action message:(NSString *)message
{
    ZONVerifyResult *r=[ZONVerifyResult new]; r.allowed=allowed; r.code=code?:@"unknown"; r.action=action?:@"disable_feature"; r.message=message?:@""; return r;
}

- (NSString *)cacheKeyForBundleID:(NSString *)bundleID { return [NSString stringWithFormat:@"ZONDylibAuth.v3.%@.%@",self.configuration.dylibKey,bundleID]; }
- (void)storeCachedResult:(NSDictionary *)json bundleID:(NSString *)bundleID
{
    NSTimeInterval grace=[json[@"offline_grace_seconds"] doubleValue]; if(grace<=0) return;
    NSMutableDictionary *copy=[json mutableCopy]; copy[@"cache_expires_at"]=@(NSDate.date.timeIntervalSince1970+grace);
    [[NSUserDefaults standardUserDefaults] setObject:copy forKey:[self cacheKeyForBundleID:bundleID]];
}
- (ZONVerifyResult *)cachedResultForBundleID:(NSString *)bundleID
{
    NSDictionary *json=[[NSUserDefaults standardUserDefaults] dictionaryForKey:[self cacheKeyForBundleID:bundleID]];
    if(!json||[json[@"cache_expires_at"] doubleValue]<NSDate.date.timeIntervalSince1970||![json[@"ok"] boolValue]) return nil;
    ZONVerifyResult *r=[self resultFromJSON:json]; r.offlineCache=YES; return r;
}
- (void)clearCachedResultForBundleID:(NSString *)bundleID { [[NSUserDefaults standardUserDefaults] removeObjectForKey:[self cacheKeyForBundleID:bundleID]]; }

+ (NSString *)currentDylibSHA256
{
    Dl_info info; if(dladdr((const void *)&ZONVerifyImageAnchor,&info)==0||!info.dli_fname) return @"";
    NSData *data=[NSData dataWithContentsOfFile:[NSString stringWithUTF8String:info.dli_fname]]; if(!data) return @"";
    unsigned char digest[CC_SHA256_DIGEST_LENGTH]; CC_SHA256(data.bytes,(CC_LONG)data.length,digest);
    NSMutableString *hex=[NSMutableString stringWithCapacity:64]; for(int i=0;i<CC_SHA256_DIGEST_LENGTH;i++) [hex appendFormat:@"%02x",digest[i]]; return hex;
}

+ (NSString *)currentAppMachOUUID
{
    const struct mach_header *header=_dyld_get_image_header(0); if(!header) return @"";
    BOOL is64=(header->magic==MH_MAGIC_64||header->magic==MH_CIGAM_64); uintptr_t cursor=(uintptr_t)header+(is64?sizeof(struct mach_header_64):sizeof(struct mach_header));
    for(uint32_t i=0;i<header->ncmds;i++){
        const struct load_command *cmd=(const struct load_command *)cursor; if(!cmd||cmd->cmdsize<sizeof(struct load_command)) break;
        if((cmd->cmd&0x7fffffff)==LC_UUID){ const struct uuid_command *uuid=(const struct uuid_command *)cmd; NSMutableString *s=[NSMutableString string]; for(int j=0;j<16;j++){ [s appendFormat:@"%02X",uuid->uuid[j]]; if(j==3||j==5||j==7||j==9) [s appendString:@"-"]; } return s; }
        cursor+=cmd->cmdsize;
    }
    return @"";
}
@end
