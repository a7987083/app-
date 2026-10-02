# ZONOE 软件源 2026092435

## 更新内容

### Dylib Device Key 支持无限多 App / Keychain

- Device Key 授权作用域继续保持 `UDID + Dylib Key`，不把 BundleID 引入设备密钥绑定。
- 同一 `UDID + Dylib Key` 现在允许多个独立 P-256 PublicKey 并存，适配不同 App、同 BundleID 不同 Keychain、重签/重装等场景。
- 数据库唯一约束由 `udid_hash + dylib_id` 调整为 `udid_hash + dylib_id + public_key_hash`。
- 已移除“存在其他已登记 Key 就直接 device_key_mismatch”的单 Key 限制。
- 新 PublicKey 仍必须通过 active UDID `auth_proof`、一次性 Challenge 和 P-256 ECDSA 签名验证后才能登记。

### 数据库长期稳定

- Device Key 不设置数量硬上限，不做第 11 个淘汰，因此 App 数量不受 10 个槽位限制。
- 已登记 Key 每次成功验证更新 `last_used_at`。
- 新 Key enrollment 时清理 365 天未使用的旧 Device Key，控制长期无效数据积累。
- 新增 MySQL 5.7 幂等迁移：`release/sql/2026092435_multi_device_keys.sql`。

### 兼容性

- 基于正式 `source-v2026092434` / `982a8ea6bdb1eca78a4fc9c3d12ca0069653b5da` 开发。
- 2434 已登记的现有 Device Key 在迁移后继续有效。
- Protocol v3 canonical 字段顺序不变。
- BundleID 仍用于 App 身份/授权业务，但不参与 Device Key enrollment 唯一身份。

## CI / 验证

- `Dylib Multi Device Keys 2435 CI` 已覆盖 PHP 7.0 syntax、MySQL 5.7 migration 双执行幂等、同一 UDID+Dylib 多 PublicKey 共存、重复 PublicKey 拒绝以及在线更新包包含 2435 migration。
- 正式发布继续经过 ZONOE Source Release Gate：完整 PHP regression、source integrity、MySQL 5.7、HTTP load、Release 生成和真实 GitHub Release 在线更新 E2E。

## 升级路径

`source-v2026092434 -> source-v2026092435`
