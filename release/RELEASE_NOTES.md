# ZONOE 软件源 2026092201

## 更新内容

本版本从稳定基线 `2026091809` 引入 **IPA Data Center v1** 与 **Dylib Verification Center v1**，并保持原在线更新协议和 Category 性能逻辑不变。

### IPA 数据中心

- OpenList 数据源管理，Token 加密保存。
- 增量/全量扫描、分页枚举、任务队列、独立 Scan Worker / Parse Worker。
- HTTP Range 读取 ZIP Central Directory，按成员读取 `Info.plist`，支持 XML plist 与 binary plist。
- IPA 元数据、Bundle ID、版本、MinimumOS、SHA256 及二进制索引。
- Mach-O 深度解析：SHA256、architectures、`LC_ID_DYLIB/install_name`。
- `fa_category` 仅允许管理员手动 preview → apply 写回，Worker 不自动写项目表。

### Dylib 验证中心

- Dylib 注册、版本管理、多游戏 BundleID 绑定、验证日志。
- 版本策略支持 `active / deprecated / testing / blocked / revoked`，客户端不写死业务状态。
- 服务端校验 UDID、卡密有效期、黑名单、BundleID、Dylib ID/版本、可选 SHA256。
- HMAC-SHA256 请求签名、timestamp + nonce 防重放、短期 session token。
- 默认离线容忍 900 秒，失败行为由服务端策略下发。
- Objective-C / iPhoneOS arm64 客户端实现已通过 Apple SDK 编译门禁。

### Worker 与运维

新增 ThinkPHP CLI：

- `php think ipa:worker`
- `php think ipa:parse-worker`
- `php think ipa:maintenance`

仓库同时提供 systemd service/timer 模板；在线更新只覆盖站点目录，不会擅自修改 `/etc/systemd/system`。

### 数据库

在线更新包新增：

- `release/sql/2026092201_ipa_data_center.sql`

迁移为 MySQL 5.7 兼容、可重复执行设计，创建 IPA / Dylib 相关表及 FastAdmin 权限菜单节点。

### 在线更新

本版本继续沿用现有 `UpdateManager / UpdateInstaller`：

- 下载更新 ZIP
- SHA256 校验
- 程序及数据库备份
- 执行 `mysql/*.sql`
- 覆盖 `program/*`
- 覆盖后 SHA256 文件校验
- 写入版本号
- 失败自动回滚

在线更新清单已经加入 IPA Data Center / Dylib Verification Center 的服务器运行文件。

`file_sign` 继续为 `f3f6e072f814d06403ce5e393967c9e2`，因为本版本未修改 `UpdateIntegrity::files()` 当前监控的 4 个关键文件。

### 验证

- PHP 7.0 兼容与 ThinkPHP CLI 真启动。
- PHP 8.x 静态/契约测试。
- MySQL 5.7.44 schema 与 migration。
- 100,000 IPA 资产规模测试。
- 多 Worker claim contention。
- OpenList HTTP 集成测试。
- Range ZIP / XML plist / binary plist。
- Mach-O thin / FAT 解析。
- Dylib HMAC 固定签名向量。
- iPhoneOS SDK arm64 Objective-C 编译。
- `2026091809 → 2026092201` 正式 GitHub Release 在线更新 E2E 作为发布门禁。

### 上线前配置

生产服务器需要设置 `IPA_SERVER_SECRET`（至少 32 bytes）后再保存 OpenList Token 或生成 Dylib 验证密钥。
