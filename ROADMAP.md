# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release: `source-v2026092435`
- Stable target: `bda2656a699e93162ea38514f21e5027fc92ece9`
- Release branch: `release/2026092435-multi-device-keys`

## 2026092435 — Multi Device Keys

### 已完成

- [x] Device Key 授权作用域保持 `UDID + Dylib Key`。
- [x] 同一授权作用域允许多个独立 PublicKey 并存。
- [x] 不限制 App / Keychain 数量，不使用固定 10 槽位。
- [x] 新 PublicKey 必须通过 active UDID auth proof、一次性 Challenge、P-256 ECDSA。
- [x] 数据库唯一键调整为 `udid_hash + dylib_id + public_key_hash`。
- [x] 365 天未使用 Device Key 清理策略。
- [x] MySQL 5.7 migration 幂等验证。
- [x] Protocol v3 / Parser V2 / RSA runtime config 旧测试契约同步完成。
- [x] Dylib Multi Device Keys 2435 CI 通过。
- [x] IPA Online Update Release Gate 通过。
- [x] ZONOE Source Release / 在线升级 E2E 通过。
- [x] 正式 Release `source-v2026092435` 已发布。
