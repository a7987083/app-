# Development Changelog

## 2026-09-24 — Candidate 2026092405

Baseline: `source-v2026092404`; development base HEAD `c27ad2347e025dd0dda1e24afda65856691fef06`.

### Dylib lifecycle

- `DylibCenter` 增加 `setDylibEnabled()` 与 `deleteDylib()`；停用只修改 `enabled` 并保留历史。
- 删除先检查 `dylib_version`、`dylib_app_binding` 和 `dylib_verify_log`；有任一历史引用即拒绝硬删并要求停用。
- 编辑时 `dylib_key` 不可修改，避免破坏旧客户端查找/HMAC 契约；验证密钥留空表示不轮换。
- 页面重排为注册 → 接入 → 游戏授权 → 版本控制 → 验证记录。
- 接入说明来自真实 `/index/dylib_verify/verify` 与 `ZONVerifyClient`，未新增接口。
- 状态、动作、result_code 仅做 UI 中文映射；底层枚举、版本规则和 BundleID 行为不变。
- 删除使用既有 `Layer.confirm` + `Fast.api.ajax`；Backend 权限链保持 `$noNeedRight=[]`。

### Tests and CI

- 新增 `tests/phase2405_dylib_lifecycle_contract_test.php`，并接入原 `tests/dylib_signing_contract.php`。
- 早期 Run `36020297000` 的测试实现暴露 PHP 字符串插值问题；已修复测试，不涉及业务代码。
- Latest code HEAD `36e9104f6fe6b26cd7809f4d064f4016114add1f`:
  - IPA Data Center CI `36020461657` — SUCCESS。
  - Regression Checks `36020461082` — SUCCESS。
  - Phase14 Production Hardening `36020461401` — SUCCESS。
  - PHP 7.0 syntax/contracts、MySQL 5.7 schema/worker/scale、Dylib security/signing/lifecycle、iPhoneOS arm64 compile 全部通过。

### Release

- 2026092405 release metadata and Final Gate prepared in one atomic commit to avoid any intermediate commit refreshing historical `source-v2026092404`.
- Formal Release / online-update E2E / Final Gate: PENDING at the time of this candidate record.
- Real BaoTa runtime/UI validation: NOT YET VERIFIED.

## 2026-09-24 — Release 2026092404

- FPM inline scan/parse, live refresh and software-source response fix released as `source-v2026092404`.
- Source Release `35964382173`, Final Gate `35964464124` and real `2403 -> 2404` GitHub Release E2E all passed.
- Real BaoTa 2404 verification remained pending when 2405 development started.

## Historical

- 2026092403: FPM inline scan release.
- 2026092402: open_basedir/management hotfix.
- 2026092401: initial Web-side worker launcher experiment.
- 2026092207: scan/parse controls regression fix.
- Historical releases are immutable.
