# Software Source Development Handoff

## Repository
- Repository: `a7987083/app-`
- Development branch: `dev/software-source-v1`
- Stable baseline branch: `main`
- Stable baseline commit: `598235962ea328c6558fe4935fe19ba552c1490d`
- Baseline source: ceshi1 2026-09-04 import (sanitized DB config)

## Current Goal
Develop the software-source backend from the current working baseline without regressing existing source, encryption, unlock, blacklist, category, or dylib/UDID behavior.

## Current Architecture / Key Entry Points
- Source API: `application/index/controller/App.php`
- Secondary source API: `application/index/controller/App-mb.php`
- App management: `application/admin/controller/Category.php`
- App add UI: `application/admin/view/category/add.html`
- App edit UI: `application/admin/view/category/edit.html`
- Site/source config: `application/extra/site.php`
- Runtime DB config is represented by `fa_config`
- App/source records are represented by `fa_category`

## Baseline Capabilities Confirmed
- `/appstore` source output
- `appstore` / `appstore_v2` selection through `APPSTORE` request header
- Optional source encryption (`opencry`)
- Source metadata: `name`, `message`, `identifier`, `sourceURL`, `sourceicon`, `payURL`, `unlockURL`
- App metadata currently emitted: `name`, `type`, `version`, `versionDate`, `versionDescription`, `lock`, `downloadURL`, `isLanZouCloud`, `iconURL`, `tintColor`, `size`
- Category types: 应用 / 游戏 / 影音 / 工具 / 插件
- Existing dylib/UDID remote settings: `dylib-control`, `dylib-notice`, `dylib-look`, `dylib-time`, `dylib-on`

## Stability Rules
1. Do not rewrite or replace the `main` baseline while development is in progress.
2. Preserve existing `/appstore` JSON keys and semantics for existing clients.
3. Preserve current encrypted `appstore` / `appstore_v2` behavior unless explicitly changing protocol compatibility.
4. Preserve blacklist, unlock/kami, locked-app download behavior.
5. Prefer additive fields and backward-compatible endpoints over schema-breaking changes.
6. Do not blindly copy historical ceshi12/ceshi1 diffs; verify current source first.

## Validation Required For Runtime Changes
- PHP syntax check for changed PHP files.
- Compare plaintext source JSON before/after for legacy keys.
- Verify encrypted source path separately for `appstore` and `appstore_v2` when touched.
- Verify locked/unlocked and blacklisted response paths when `App.php` is touched.
- Verify admin add/edit compatibility when `fa_category` fields are touched.

## Current Status
- Baseline locked.
- Development branch created.
- No runtime behavior changed yet.

## Next Task
Implement the first functional software-source enhancement on this branch using additive, backward-compatible changes only; update this file and `PROJECT_STATE.json` after implementation and validation.
