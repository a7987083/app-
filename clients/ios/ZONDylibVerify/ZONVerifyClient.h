#import <Foundation/Foundation.h>

NS_ASSUME_NONNULL_BEGIN

typedef NSString * _Nullable (^ZONUDIDProvider)(void);
typedef NSString * _Nullable (^ZONLicenseCodeProvider)(void);

@interface ZONVerifyConfiguration : NSObject
/// Legacy/direct verification endpoint. Kept as the final fallback so existing
/// endpoint fallback behavior remains available.
@property (nonatomic, copy) NSURL *endpointURL;
/// Independent full bootstrap config URLs. Use more than one provider/domain.
@property (nonatomic, copy) NSArray<NSURL *> *bootstrapURLs;
@property (nonatomic, copy) NSString *dylibKey;
@property (nonatomic, copy) NSString *dylibVersion;
@property (nonatomic, copy) NSString *dylibBuild;
/// Server RSA public key embedded by Codegen. The matching private key never
/// leaves the verification server.
@property (nonatomic, copy) NSString *serverPublicKeyPEM;
@property (nonatomic, copy) NSString *serverKeyID;
@property (nonatomic, copy) ZONUDIDProvider udidProvider;
/// Deprecated compatibility property. Protocol v3.1 no longer sends card codes
/// for device-key enrollment; the client obtains a short-lived auth_proof from
/// /index/index/apiface using the already-authorized UDID.
@property (nonatomic, copy, nullable) ZONLicenseCodeProvider licenseCodeProvider;
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

/// Executes protocol v3.1 verification. The client first obtains a short-lived
/// active-UDID auth_proof from /index/index/apiface, then obtains a one-time
/// challenge, signs auth_proof + challenge + app identity with the per-device
/// P-256 private key in Keychain, and finally verifies/enrolls the device key.
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
