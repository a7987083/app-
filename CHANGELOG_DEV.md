# Development Changelog

## 2026-09-24 — Release 2026092405

Baseline: `source-v2026092404`; release commit `f2cb8536b2a5196b4dab1c135c74033f740ed398`.

### Dylib lifecycle

- `DylibCenter` 增加 `setDylibEnabled()` 与 `deleteDylib()`；停用只修改 `enabled` 并保留历史。
- 删除先检查 `dylib_version`、`dylib_app_binding` 和 `dylib_verify_log`；有任一历史引用即拒绝硬删并要求停用。
- 编辑时 `dylib_key` 不可修改，避免破坏旧客户端查找/HMAC 契约；验证密钥留空表示不轮换。
- 页面重排为注册 → 接入 → 游戏授权 → 版本控制 → 验证记录。
- 接入说明来自真实 `/index/dylib_verify/verify` 与 `ZONVerifyClient`，未新增接口。
- 状态、动作、result_code 仅做 UI 中文映射；底层枚举、版本规则和 BundleID 行为不变。
- 删除使用既有 `Layer.confirm` + `Fast.api.ajax`；Backend 权限/CSRF 链保持不变。

### Verification and release

- Pre-release exact-code HEAD `36e9104f6fe6b26cd7809f4d064f4016114add1f`:
  - IPA Data Center CI `36020461657` — SUCCESS。
  - Regression Checks `36020461082` — SUCCESS。
  - Phase14 Production Hardening `36020461401` — SUCCESS。
  - PHP 7.0、MySQL 5.7、Dylib signing/lifecycle/security、iPhoneOS arm64 compile 全部通过。
- Release metadata was committed atomically as `f2cb8536...` so no intermediate commit could refresh historical `source-v2026092404`.
- Final IPA Online Update Release Gate `36021185353` — SUCCESS。
- ZONOE Source Release `36021185414` — SUCCESS。
- Package/Release — SUCCESS。
- Real GitHub Release online-update E2E (`source-v2026092404 -> source-v2026092405`) — SUCCESS。
- Release `source-v2026092405` targets `f2cb8536b2a5196b4dab1c135c74033f740ed398`.
- Release ZIP `zonoe-online-update.zip`: 208933 bytes, SHA256 `abc851d4e63fd98b78eda7ce06ff9cac73b5909380efbef3511a529bc949e0f4`, asset ID `586260722`.
- CI Artifact `zonoe-source-2026092405-online-update`: ID `10816991706`, 202158 bytes, digest `sha256:91f2d63221448f9f858035c1672a17abe3082399db9b326d382a4ff7e2bb1a55`.
- Real BaoTa Dylib lifecycle/UI validation: NOT YET VERIFIED.

## 2026-09-24 — Release 2026092404

- FPM inline scan/parse, live refresh and software-source response fix released as `source-v2026092404`.
- Source Release `35964382173`, Final Gate `35964464124` and real `2403 -> 2404` GitHub Release E2E all passed.
- Historical release remains immutable.
