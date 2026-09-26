# ZONOE 软件源 2026092414

## 更新内容

### 运维中心备份管理

本版本以 `source-v2026092413` 为升级基线，重点补齐更新运维中心的备份可视化与安全清理能力。

- 新增备份明细列表：显示备份 ID、创建时间、文件数、数据库备份大小、总占用与保护状态。
- 新增备份汇总：总备份数量、总占用、可删除占用、磁盘总容量与可用容量。
- 支持单个删除、勾选批量删除、删除全部未保护备份。
- 保留更新历史回滚保护：仍被成功更新历史引用的备份禁止删除，前端不可选，后端再次校验拒绝删除。
- 删除操作仅接受内部备份 ID，并使用 `realpath` 与备份根目录边界检查，避免任意路径删除。
- 原有 30 天安全清理继续保留。

### API 请求日志清理

- `API 接口 -> 请求日志` 保留原“刷新日志”。
- 新增“删除全部日志”。
- 前端要求两次确认；后端要求固定确认值 `DELETE_ALL_API_LOGS`。
- 只清空 `fa_api_request_log`，不会删除 Dylib 验证日志、API 配置、自定义 API 或其他业务数据。
- 删除完成后返回实际删除条数。

### 自动在线发布

仓库新增中央 `Auto Online Release Gate`：

- 对 `feature/20*` 与 `release/**` 分支统一生效；
- 等待同一 commit 的其他 CI 全部结束；
- 任意 CI 失败则不发布；
- 全部 CI 成功后校验 `VERSION`、`public/update/ver.txt`、`ver.json`；
- 若 `source-v<VERSION>` 已存在则幂等跳过；
- 新版本自动触发正式 `ZONOE Source Release`，生成 `zonoe-online-update.zip`、SHA256、GitHub Release，并继续真实在线升级 E2E。

发布策略已写入 `RELEASE_POLICY.md` 与 `release/auto-release.env`，后续版本不再依赖人工记住发在线更新。

### 自动验证

2414 开发 CI 已覆盖：

- PHP 7.0 语法检查；
- 运维中心原有回归；
- 备份管理 / 请求日志清理契约；
- 更新器 failure injection；
- 更新 runtime 回归；
- 更新 atomicity 回归。

开发 CI `Phase14.3 Update Operations #32 / Run 36256318869` 已成功；中央自动发布 Gate 也已完成首次幂等验证。

### 兼容性

2414 不改变：

- Dylib Protocol v1 / v2 canonical；
- Dylib 在线验证 wire contract；
- Runtime Config 签名；
- 卡密/授权现有业务协议；
- 现有数据库结构。

目标升级路径：`source-v2026092413 -> source-v2026092414`。
