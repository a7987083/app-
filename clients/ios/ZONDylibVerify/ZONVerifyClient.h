#import <Foundation/Foundation.h>

NS_ASSUME_NONNULL_BEGIN

typedef NSString * _Nullable (^ZONUDIDProvider)(void);
typedef NSString * _Nullable (^ZONLicenseCodeProvider)(void);

@interface ZONVerifyConfiguration : NSObject
@property (nonatomic, copy, nullable) NSURL *endpointURL;
@property (nonatomic, copy) NSArray<NSURL *> *bootstrapURLs;
@property (nonatomic, copy) NSString *dylibKey;
@property (nonatomic, copy) NSString *dylibVersion;
@property (nonatomic, copy) NSString *dylibBuild;
@property (nonatomic, copy) NSString *serverPublicKeyPEM;
@property (nonatomic, copy) NSString *serverKeyID;
@property (nonatomic, copy) ZONUDIDProvider udidProvider;
@property (nonatomic, copy) ZONLicenseCodeProvider licenseCodeProvider;
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
- (void)verifyWithCompletion:(void (^)(ZONVerifyResult *result))completion;
+ (NSString *)currentDylibSHA256;
+ (NSString *)currentAppMachOUUID;
@end

NS_ASSUME_NONNULL_END
