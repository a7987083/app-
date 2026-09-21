#import <Foundation/Foundation.h>

NS_ASSUME_NONNULL_BEGIN

typedef NSString * _Nullable (^ZONUDIDProvider)(void);

@interface ZONVerifyConfiguration : NSObject
@property (nonatomic, copy) NSURL *endpointURL;
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
@property (nonatomic, assign) NSTimeInterval offlineGraceSeconds;
@property (nonatomic, assign) NSTimeInterval serverTime;
@property (nonatomic, assign, getter=isOfflineCache) BOOL offlineCache;
@end

@interface ZONVerifyClient : NSObject

- (instancetype)initWithConfiguration:(ZONVerifyConfiguration *)configuration NS_DESIGNATED_INITIALIZER;
- (instancetype)init NS_UNAVAILABLE;

/// Executes one verification request. The UDID is supplied by the host/injection
/// environment through configuration.udidProvider; this SDK does not invent or
/// derive a replacement identifier.
- (void)verifyWithCompletion:(void (^)(ZONVerifyResult *result))completion;

/// SHA256 of the actual image containing this SDK code. Returns an empty string
/// when the dyld image path cannot be resolved or the image cannot be read.
+ (NSString *)currentDylibSHA256;

@end

NS_ASSUME_NONNULL_END
