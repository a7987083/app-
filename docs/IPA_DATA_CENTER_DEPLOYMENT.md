# IPA Data Center / Dylib Verification Deployment

Baseline: `source-v2026091809` / `21307ba5ec9b65ce0b5f0643555ccc7f7bec0ad9`

## 1. Database

Fresh install:

```bash
mysql -uUSER -p DB < database/ipa_data_center_v1.sql
mysql -uUSER -p DB < database/ipa_data_center_menu_v1.sql
```

Existing v1 database that predates request signing:

```bash
mysql -uUSER -p DB < database/ipa_data_center_v1_1_request_signing.sql
mysql -uUSER -p DB < database/ipa_data_center_menu_v1.sql
```

No migration in this module drops or rewrites `fa_category`, `fa_kami`, `fa_black`, or the legacy dylib endpoints.

## 2. Required environment

Set a random server-side master secret with at least 32 bytes:

```ini
[ipa]
server_secret = "replace-with-a-random-secret-at-least-32-bytes"
verify_timestamp_skew = 300
session_ttl = 900
offline_grace = 900
verify_log_retention_days = 30
```

`server_secret` encrypts OpenList tokens and per-dylib request verification keys. Do not commit the production value to Git.

## 3. OpenList

In FastAdmin -> IPA 数据中心:

1. Add the OpenList base URL, for example `https://openlist.example.com`.
2. Set the IPA root path, for example `/IPA`.
3. Add the OpenList token when required.
4. Start incremental or full discovery.

The discovery worker enumerates directories. The parse worker uses OpenList file metadata/raw URLs for HTTP Range parsing. A temporary OpenList `raw_url` is never written to `fa_category.bt1a`; manual writeback uses the stable `/d/<path>` URL.

## 4. Workers

Adjust `WorkingDirectory`, PHP path, user/group in `deploy/systemd/*.service` for the actual host.

```bash
sudo cp deploy/systemd/zonoe-ipa-worker.service /etc/systemd/system/
sudo cp deploy/systemd/zonoe-ipa-parse-worker.service /etc/systemd/system/
sudo cp deploy/systemd/zonoe-ipa-maintenance.service /etc/systemd/system/
sudo cp deploy/systemd/zonoe-ipa-maintenance.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now zonoe-ipa-worker.service
sudo systemctl enable --now zonoe-ipa-parse-worker.service
sudo systemctl enable --now zonoe-ipa-maintenance.timer
```

Scale discovery and parse workers independently if required. Before adding multiple instances, validate MySQL row-lock behavior on the production 5.7 configuration and monitor queue duplication/retries.

## 5. Dylib verification setup

In FastAdmin -> IPA 数据中心 -> Dylib 验证中心:

1. Generate a verify secret.
2. Save the Dylib Key and copy that secret into the corresponding Objective-C dylib build configuration.
3. Add a version/build and choose one of `active`, `deprecated`, `testing`, `blocked`, `revoked`.
4. Bind every allowed game Bundle ID.
5. Optionally record the official dylib SHA256 for integrity matching.
6. Choose fail action and offline grace globally, per version, or per Bundle ID override.

The server validates request HMAC before reserving a nonce or querying UDID authorization.

## 6. Objective-C client

Source: `clients/ios/ZONDylibVerify/`.

The client requires the existing project/injection environment to provide the real UDID via `udidProvider`. It does not substitute IDFV/IDFA or fabricate an identifier.

Link Foundation, Security and CommonCrypto. Configure the endpoint as:

```text
POST /index/dylib_verify/verify
```

The client stores successful offline authorization in Keychain. Explicit server rejection clears the cache; network/server unavailability may use the cached authorization only inside `offline_grace_seconds` (default 900 seconds).

## 7. Manual fa_category writeback

Writeback is never executed by discovery or parse workers.

Administrator flow:

```text
parsed IPA -> choose fa_category -> preview diff -> select fields -> apply
```

Allowed fields in v1:

- `name`
- `nickname`
- `bt1a`
- `bt2a`

On a real change, existing 1809 cache/revision behavior remains intact through `SourceAppRepository::forget()` and `SourceChangeLog::record()`.

## 8. Verification before production

At minimum:

```bash
php think ipa:worker --once
php think ipa:parse-worker --once
php think ipa:maintenance
```

Then validate a real OpenList IPA, manual writeback preview/apply, one valid dylib request, and negative cases for replay, invalid signature, wrong BundleID, expired UDID, blocked/revoked version and SHA256 mismatch.

The current GitHub CI is a code/contract gate. It does not substitute for production MySQL/OpenList/iOS device integration testing.
