# ZONOE 软件源 2026092426

## 更新内容

### IPA Parser V2 Clean Rebuild

本版本以 `source-v2026092425` / `b4111ce2995a4a39e3b7f4c065f37785ae2d657d` 为直接基线，重构 IPA 解析执行链。目标是先彻底移除旧 Parser 的重型同步逻辑，再以轻量、可隔离的 Parser V2 接管基础 IPA metadata 解析。

### 旧解析逻辑移除

- PHP-FPM shutdown handler 不再执行 IPA 解析，只保留扫描队列兼容处理；解析任务不会再长期占用 FPM worker。
- 2425 的旧 `IpaParserService` 实现已删除；在线升级时该路径由轻量兼容壳覆盖，旧 Mach-O/Hash 解析代码不会残留为可执行实现。
- Parser V2 不再在 metadata 阶段扫描全部 Framework/dylib，不执行 Mach-O architecture/install-name/UUID enrichment，也不计算小二进制 SHA256。
- Parse Worker 不再自动执行 `IpaCompareService::refreshAsset()`；数据库比对继续保留为显式业务能力。
- 历史 window/hour/day 配置字段保留用于升级兼容，但 Parser V2 不再执行这些 COUNT 查询，也不按这些旧额度限速。

### Parser V2

- 新增 `application/common/library/Ipa/IpaParserV2Service.php`。
- 通过 OpenList 获取当前对象的 `raw_url` 与最新 size。
- 使用 HTTP Range 读取 IPA ZIP 的 EOCD、Central Directory、目标 local header 与 `Payload/*.app/Info.plist`。
- Info.plist 解包上限为 4 MiB。
- 只提取 `CFBundleIdentifier`、`CFBundleDisplayName/CFBundleName`、`CFBundleShortVersionString`、`CFBundleVersion`、`MinimumOSVersion`、`CFBundleExecutable`。
- 成功后使用短数据库事务更新 `ipa_asset`；旧 binary/app identity/compare 派生数据在 V2 成功接管该资产时清理，避免展示陈旧的 2425 派生结果。
- 单个 IPA 失败只标记该资产 `parse_failed`，不会终止整个 Worker。

### CLI Worker / 调度

- `ipa:parse-worker` 改为 Parser V2 CLI Worker。
- 继续支持 `--once`、`--scheduled` 与 `--sleep` 参数，兼容现有运维入口。
- 复用 `deploy/systemd/zonoe-ipa-parse-worker.service`，由 timer 每分钟执行一次 scheduled batch。
- `parsing` 超过 600 秒可回收为 `discovered`，防止异常退出永久占住资产。

### 保持不变

- IPA 扫描与 OpenList 数据源。
- `ipa_asset` 资产发现记录与 IPA 搜索。
- 增量/全量扫描。
- IPA 删除、清空解析结果语义。
- 软件源数据库手工比对/写回能力。
- Dylib Protocol v1/v2、卡密/授权协议。
- 在线更新 SHA256、备份、数据库迁移和失败回滚链。

## CI

新增 `IPA Parser V2 2426 CI`，覆盖：

- PHP 7.0 语法检查；
- 旧 Parser 重型实现不存在；
- 兼容壳只能转发到 V2；
- FPM launcher 不包含 parse drain；
- V2 不引用 MachOInspector/IpaCompareService/binary hash；
- Range ZIP / plist 原有回归；
- ThinkPHP CLI command 注册；
- systemd service/timer 调度契约。

V2 主实现已经通过专项 CI。正式 2426 发布仍由 Auto Online Release Gate 等待同一最终提交上的全部 sibling CI 后刷新 GitHub Release，并执行真实在线升级 E2E。

## 升级路径

`source-v2026092425 -> source-v2026092426`

## 尚未声明

自动化 CI 不等同于生产环境真实性能结果。本版本发布后仍需在实际 OpenList / MySQL / PHP-FPM 环境验证解析耗时、Range 请求量、FPM 占用和后台页面体验。
