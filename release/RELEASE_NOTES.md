# ZONOE 软件源 2026091709

## 更新内容

### 回滚到 2026091706 稳定运行时代码

- **2026091707、2026091708 正式作废（Deprecated）**，不再作为稳定版、部署基线或后续开发基线。
- 2026091709 不是在 1708 上继续打补丁，而是直接从 `source-v2026091706` 正式 Release commit `1315f7974f9220d1b422ef69686ad2110f5e549e` 重新建立。
- `application/index/controller/App.php` 恢复 1706 实现：重新直接读取 `fa_category`，不再调用 1707 引入的 `SourceAppRepository` / `SourcePerformance`。
- `SourceResponse.php` 恢复 1706 实现：移除代码层 HTTP gzip。
- `SourceEncryptionProvider.php` 恢复 1706 实现：移除 legacy bkey 持久缓存改动。
- 1707/1708 新增的 `SourceAppRepository.php`、`SourcePerformance.php` 如果已经存在于生产服务器，可作为未引用残留文件保留；1709 的运行时代码不会加载它们。
- `appstore` / `appstore_v2` 的客户端协议、JSON 字段和 1706 原有加密兼容逻辑保持不变，不要求客户端更新。

## 保留的 1706 功能

- 删除卡密时同步清理对应 `fa_kami_app.kami_id` 映射；卡密仅到期不清映射。
- 删除 App 时同步清理对应 `fa_kami_app.app_id` 映射；隐藏、停用、编辑不清映射。
- 清空换绑记录、授权事件后重新进入授权总览并禁止旧页面缓存。
- 保持续费入口、卡密用途、授权叠加、换绑额度、`apiface` 签名等 1706 已验证逻辑。

## 数据库

- 本版本无数据库结构变更。
- 不删除、不回滚任何用户业务数据。
- 已在 1707/1708 期间产生的卡密、授权、App 数据继续保留。

## 废弃版本

- `2026091707`：**DEPRECATED / 作废，请勿部署**。
- `2026091708`：**DEPRECATED / 作废，请勿部署**。
- 后续开发统一从 `2026091709`（1706 runtime baseline）继续。

## 验证要求

- PHP 7.0 全回归必须通过。
- MySQL 5.7 迁移链必须通过。
- 在线更新 ZIP 必须包含 1706 版本的 `App.php`、`SourceResponse.php`、`SourceEncryptionProvider.php` 等运行文件。
- 必须发布 `zonoe-online-update.zip` 与 SHA256。
- 必须通过从上一正式 Release 到 2026091709 的真实 GitHub 在线升级 E2E。

## 在线更新

- 正式版本：`2026091709`
- GitHub Release：`source-v2026091709`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
- 已在 2026091707 / 2026091708 的服务器可直接在线升级到 2026091709 完成回滚。
