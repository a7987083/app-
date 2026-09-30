# ZONOE 软件源 2026092427

## 更新内容

### IPA Parser：迁移 ipaxiazaizhan- 已验证解析模型

本版本以 `source-v2026092426` 为在线升级基线，目标不是继续扩展 2426 的 systemd-only 调度思路，而是把 `a7987083/ipaxiazaizhan-` 中已经验证稳定的 IPA metadata 解析模型迁移到 `app-`，并适配现有 ThinkPHP / OpenList / ipa_asset / 后台 UI 数据契约。

### 应用内触发，不再依赖 systemd 才能解析

- IPA 扫描队列处理完成后，由应用侧 `IpaWorkerLauncher::ensureParseWorker()` 检查是否存在待解析资产，并后台启动 `php think ipa:parse-worker --scheduled`。
- Parser 仍然运行在独立 CLI PHP 进程，不回退到 PHP-FPM shutdown 中执行重型解析，因此不会重新长期占用 FPM worker。
- systemd service/timer 继续保留为可选运维入口，但不再是 IPA Parser 能工作的唯一前提。
- 若 PHP 环境禁用了 `exec`/相关进程启动能力，会返回明确 launcher 状态，不会把解析逻辑塞回 FPM。

### MD5 内容指纹与解析结果复用

- 扫描 OpenList 文件时读取 MD5 内容指纹，并兼容 `hash_info.md5`、`hashinfo` 与直接 `md5` 字段。
- 内容指纹复用现有 `ipa_asset.etag` 持久化，不要求在线升级新增数据库字段。
- 当 MD5 未变化时，不会仅因路径时间戳变化重复做完整 Range 解析。
- Parser V2 在真正发起 Range 请求前，会查找相同 MD5 且已经成功解析的 IPA；命中后直接复用 Bundle ID、应用名、版本、Build、MinimumOSVersion 等 metadata。
- 相同内容复用时 `range_bytes=0`，避免同一 IPA 被多个路径/软件源引用时反复读取远端 ZIP。

### OpenList 目录缓存

- 新增 30 分钟 OpenList 目录缓存，行为对齐参考项目。
- 缓存按 OpenList endpoint、token 指纹、path、page、per_page 隔离，并持久化在 runtime 目录，FPM/CLI 进程间可复用。
- 增量扫描可以使用 30 分钟缓存，减少重复 `/api/fs/list` 请求。
- **全量扫描明确绕过目录缓存并强制 refresh**，保证“全量扫描”仍然具有真实重新扫描语义，不会拿旧目录快照冒充全量扫描。

### Range Parser

- 继续采用 Info.plist-only 快速解析路径，只读取 `Payload/*.app/Info.plist`。
- HTTP Range 以固定 256 KiB block 为单位缓存；同一个 block 在一次解析中重复 seek 时直接复用，不再重复发 HTTP 请求。
- 单个 IPA 的实际网络 Range 读取预算限制为 16 MiB，异常 ZIP 或超大对象不会无限读取。
- 只提取：`CFBundleIdentifier`、`CFBundleDisplayName/CFBundleName`、`CFBundleShortVersionString`、`CFBundleVersion`、`MinimumOSVersion`、`CFBundleExecutable`。
- metadata 解析阶段不扫描全部 Framework/dylib，不执行 Mach-O architecture/install-name/UUID enrichment，不计算二进制 SHA256，也不自动执行 `IpaCompareService`。

### 有界批次与失败冷却

- `ipa:parse-worker --scheduled` 改为有界批次，不再无限 drain 当前队列。
- 新增 `--limit`，范围 1–20，scheduled 默认单轮最多 20 个 IPA。
- 单个 IPA 解析失败只标记当前资产 `parse_failed`，不阻塞后续 IPA。
- `parse_failed` 采用 30 分钟 cooldown，时间到后才重新进入 discovered 队列，避免坏 IPA 高频重试打爆远端请求。
- 超时遗留的 `parsing` 状态仍会回收，避免异常退出后永久占用资产。

### 保持不变

- OpenList 数据源配置与 IPA 扫描发现。
- `ipa_asset` 资产记录、IPA 搜索、删除与后台现有 UI 数据契约。
- 增量扫描与真正的全量扫描语义。
- “清空解析结果”继续只清解析派生数据，不删除 IPA 扫描/发现记录。
- 软件源数据库手工比对/写回能力继续保留，但不阻塞 metadata Parser。
- Dylib、卡密、授权协议和其它现有后台功能不在本次重构范围内。

## CI / 验证

最终候选代码已通过 `IPA Parser V2 2427 Migration CI`，覆盖：

- PHP 7.0 语法；
- 应用内 Parser 启动链；
- 30 分钟 OpenList 目录缓存；
- MD5 指纹与相同 MD5 metadata 复用；
- 256 KiB Range block cache 与 16 MiB 预算；
- 30 分钟 parse_failed cooldown；
- scheduled 有界批次；
- 全量扫描强制绕过目录缓存；
- Info.plist-only 契约；
- Range ZIP / plist 回归；
- ThinkPHP CLI 命令注册。

正式发布流水线前置门已验证 PHP 7.0 全回归、MySQL 5.7 migration 与 HTTP 并发负载矩阵。发布元数据修正后将重新执行 canonical Release 与真实 GitHub Release 在线升级 E2E；在该 E2E 成功前，不把本版本标记为生产环境已验收。

## 升级路径

`source-v2026092426 -> source-v2026092427`

## 尚未声明

CI 与在线升级 E2E 不等同于生产服务器真实性能验收。正式部署后仍需观察实际 OpenList 请求量、Range bytes、解析耗时、失败率和后台交互体验。
