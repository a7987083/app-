# ZONOE 软件源 2026091907

## 更新内容

本版本基于 `2026091906`，在保留上一版 `/appstore` 内存优化成果的基础上，完成 `appstore_v2` CPU 第一阶段优化，并将 IPA 管理中心后台交互统一迁移到 FastAdmin 原生 Table/Form 生命周期。既有授权、缓存隔离、OpenList 服务层和 appstore/appstore_v2 wire protocol 语义保持不变。

### 1. appstore_v2 CPU 第一阶段优化

- `application/common/library/SourceEncryptionProvider.php` 保留 RC4、RSA PKCS#1 v1.5、V2 magic、字节序、alphabet 与 variable-width codec 规则不变。
- V2 路径由“先完整生成 RC4 payload，再重新全量扫描 payload 编码”调整为 RC4 输出直接馈入 V2 codec state，去掉完整中间 `$payload` 字符串。
- 减少大字符串写入、二次遍历和内存带宽占用，目标是在单核 PHP 7.0 环境缩短 V2 加密阶段 CPU 满载持续时间。
- 保留 reference/equivalence contract，确保优化实现与原协议输出语义一致。

### 2. IPA 管理中心 FastAdmin 化

- Dashboard、Metadata、Binding、Task、Setting、Writeback、Governance 页面统一接入 FastAdmin 的 BootstrapTable / Form 生命周期。
- Setting 页改为标准 FastAdmin Form：使用 validator、CSRF token 和标准 submit，不再由自定义保存按钮单独拼 AJAX 生命周期。
- “测试连接”改为直接测试当前表单输入，不再只测试磁盘中已经保存的旧 OpenList 配置。
- Metadata / Binding / Task / Governance 的列表刷新与操作事件收回 FastAdmin Table/event 模式，减少手写 `<tbody>`、字符串拼接和重复刷新逻辑。
- Governance 移除页面内独立 inline `require()` 生命周期，统一由 IPA 后台 JS 控制器管理，同时保留原治理、恢复、lifecycle、metrics、retention 等业务服务和权限边界。

### 3. 兼容与安全边界

- 不修改授权判定、paid `downloadURL` 裁剪、entitlement cache 隔离、动态公告 sentinel、Nuosike fallback 和 OpenList 安全存储语义。
- 不修改现有 Phase 20 数据库 schema 与底层 Service 业务规则，仅重构后台表现层和 V2 加密执行路径。
- PHP 7.0 兼容语法保持不变。

### 4. CI / 发布验证

- PHP 7.0 lint / regression contracts。
- Phase 20 OpenList HTTP E2E 与 updater rollback E2E。
- MySQL 5.7 migrations 双次幂等验证。
- `/appstore` release-gating concurrency matrix。
- GitHub Release package build / SHA256 / online-update E2E。

### 发布与在线更新

- 版本由 `2026091906` 升级为 `2026091907`，确保现有在线更新面板能够识别本轮 V2 CPU 与 IPA FastAdmin 重构为新版本。
- 目标 GitHub Release 标签：`source-v2026091907`。
- 在线更新包继续由现有 `release/online-update-files.txt` + `tools/build_online_update.php` 生成，不修改 `ZONOE Source Release` workflow 语义。
- `VERSION`、`public/update/ver.txt`、`ver.json` 同步更新到 `2026091907`。
- 本轮未修改 `UpdateIntegrity::files()` 中签名文件，`file_sign` 继续使用 `8be29c04c34ba1d1cc7ec77d392b2bee`，正式 workflow 会再次计算并严格校验。

### 发布后验证

在线更新到 `2026091907` 后：

- 确认 IPA 管理中心 Setting 保存与“测试连接”均正常，不再出现原自定义按钮 Error。
- 检查 Metadata / Binding / Task / Governance 的 FastAdmin 表格分页、刷新、操作和权限行为。
- 对真实 9 MiB 级 `appstore_v2` 比较 `encryption_ms`、`total_ms` 与 CPU 100% 持续时间。
- 继续检查 PHP error log 是否存在 OOM、fatal error 或更新回滚异常。
