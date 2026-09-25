#import <Foundation/Foundation.h>

NS_ASSUME_NONNULL_BEGIN

typedef NSString * _Nullable (^ZONUDIDProvider)(void);

@interface ZONVerifyConfiguration : NSObject
/// Legacy/direct verification endpoint. Kept as the final fallback so existing
/// integrations remain usable while bootstrap discovery is rolled out.
@property (nonatomic, copy) NSURL *endpointURL;
/// Independent full bootstrap config URLs. Use more than one provider/domain.
@property (nonatomic, copy) NSArray<NSURL *> *bootstrapURLs;
@property (nonatomic, copy) NSString *dylibKey;
@property (nonatomic, copy) NSString *dylibVersion;
@property (nonatomic, copy) NSString *dylibBuild;
@property (nonatomic, copy) NSString *verifySecret;
@property (nonatomic, copy) ZONUDIDProvider udidProvider;
@property (nonatomic, assign) NSTimeInterval requestTimeout;
@end

@interface ZONVerifyResult : NSObject
@property (nonatomic, assign, getter=isAllowed) BOOL allowed;
@property (nonatomic, copy) NSString *code;
@property (nonatomic, copy) NSString *action;
@property (nonatomic, copy) NSString *message;
@property (nonatomic, copy) NSString *token;
@property (nonatomic, copy) NSString *accessLevel;
@property (nonatomic, copy) NSDictionary *permissions;
@property (nonatomic, copy) NSDictionary *appIdentity;
@property (nonatomic, copy) NSDictionary *appUpdate;
@property (nonatomic, copy, nullable) NSDictionary *notice;
@property (nonatomic, assign) NSInteger protocolVersion;
@property (nonatomic, assign) NSTimeInterval offlineGraceSeconds;
@property (nonatomic, assign) NSTimeInterval serverTime;
@property (nonatomic, assign, getter=isOfflineCache) BOOL offlineCache;
@end

@interface ZONVerifyClient : NSObject

- (instancetype)initWithConfiguration:(ZONVerifyConfiguration *)configuration NS_DESIGNATED_INITIALIZER;
- (instancetype)init NS_UNAVAILABLE;

/// Executes one verification request. Protocol v2 automatically includes the
/// host App executable, Mach-O UUID and version/build. The UDID continues to be
/// supplied by the host/injection environment.
- (void)verifyWithCompletion:(void (^)(ZONVerifyResult *result))completion;

/// SHA256 of the actual image containing this SDK code. Returns an empty string
/// when the dyld image path cannot be resolved or the image cannot be read.
+ (NSString *)currentDylibSHA256;

/// UUID from LC_UUID of the running main executable. This is combined with
/// parsed server-side IPA identity; BundleID alone is never sufficient for a
/// scope=3 App authorization.
+ (NSString *)currentAppMachOUUID;

@end

NS_ASSUME_NONNULL_END
