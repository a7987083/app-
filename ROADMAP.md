# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release: `source-v2026092434`
- Stable target: `982a8ea6bdb1eca78a4fc9c3d12ca0069653b5da`
- Current development: `2026092435`
- Development branch: `work/2026092435-multi-device-keys`

## 2026092435 — Multi Device Keys

### 目标

- [x] Device Key 授权作用域保持 `UDID + Dylib Key`，不引入 BundleID 绑定。
- [x] 同一作用域允许多个独立 PublicKey 并存，支持不同 App、不同 Keychain、重签实例。
- [x] 新 PublicKey 仍要求 active UDID auth proof、一次性 Challenge、P-256 ECDSA 签名。
- [x] Device Key 不设置数量上限，不做第 11 个淘汰。
- [x] 365 天未使用的 Device Key 自动清理。
- [x] 数据库唯一键改为 `udid_hash + dylib_id + public_key_hash`。
- [x] 增加 MySQL 5.7 / PHP 7.0 / 在线更新包专项 CI 定义。
- [ ] CI 实际运行验证。
- [ ] 正式发布 `source-v2026092435`。
- [ ] 生产环境真实客户端回归。
