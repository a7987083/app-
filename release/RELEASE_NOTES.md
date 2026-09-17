# ZONOE 软件源 2026091803

## Phase 19.3 — 高并发性能优化 + API 管理中心 + 授权日志修复

本版本以 `2026091802` 为稳定基线。客户端协议与原添加源地址保持不变，继续使用原 `/appstore`；远程 Dylib API 本版仅纳入统一管理，不重构其内部业务逻辑。

## 更新内容

### 授权中心清空修复

- 修复“清空换绑记录”数据库实际删除成功、前端却提示“清空换绑记录失败”的问题。
- 修复“清空授权事件”数据库实际删除成功、前端却提示“清空授权事件失败”的问题。
- 根因是 FastAdmin `$this->success()` 通过 `HttpResponseException` 中断请求，而旧代码把成功响应包在 `catch (\Exception)` 范围内，导致成功被再次捕获成失败。
- 成功响应现在位于数据库异常处理范围之外，前端 FastAdmin AJAX 成功回调可正常执行，页面状态会自动刷新。

### 系统设置 — API 管理中心

系统设置新增第三个页签“API接口”，并按项目结构提供：

- API列表
- 新增API
- 编辑API
- 测试API
- 请求日志

API 列表显示名称、完整接口地址、Method、来源、鉴权、状态、今日请求、最后请求、平均耗时和操作。

本版只管理项目自己的接口，不纳入 FastAdmin 原生 `/api/*`：

- `/appstore`
- `/index/index/dylib`
- `/index/index/apiface`
- `/unbind`
- `/unbind/query`
- `/license`

完整 URL 根据当前访问域名自动生成，不写死域名。

### API 真开关与安全自定义别名

- 六个项目系统 API 接入统一运行时开关；关闭后请求会真实停止进入业务逻辑并返回 503。
- 开关状态使用短时共享缓存，数据库/缓存异常时 fail-open，避免升级中断导致软件源不可用。
- 系统 API 不允许删除，只允许开启/关闭。
- 自定义 API 使用 `/project-api/<slug>` 安全别名，只能映射到现有项目处理器。
- 后台不提供任意 PHP 执行能力，不会把 API 管理页变成在线代码执行入口。
- API 请求日志记录 endpoint、Method、路径、IP、HTTP 状态、耗时与时间，不记录请求正文和卡密内容。

### Phase 19.3 性能优化

- `fa_kami` 新增 UDID/有效授权复合索引和卡密查询索引。
- `fa_black` 新增 UDID 查询索引。
- `fa_kami_app` 新增卡密-App 映射索引。
- `SourceAppRepository` 行缓存由 15 秒提升到 120 秒；仍由 generation/revision 主动失效，后台正常增删改 App 后不会等待 TTL。
- 加密路径新增静态 JSON 片段缓存：大 App 列表不再每个请求完整重新 JSON 编码；每次仅重新拼接请求级 `UDID/Time`，随后继续走原加密流程。
- 最终加密结果不共享缓存，不会跨用户复用动态字段。
- 性能日志新增 Legacy 缓存命中状态。

### 2 万 App 验证

Phase 19.3 CI 使用 20,000 条模拟 App 进行真实映射和 JSON 构建：

- App 数：20,000
- JSON 大小约 6.9 MB
- 映射约 77 ms
- 原始 JSON 编码约 49 ms
- 峰值内存约 63.8 MB
- 新加密前 JSON 构建结果与旧 `json_encode()` 字节完全一致

该测试用于验证大数据量兼容性，不代表生产环境 10 万用户或特定并发 RPS 的容量承诺。

## 兼容性

- 客户端无需更新。
- 软件源地址无需修改。
- Legacy `/appstore` 返回字段保持不变。
- Guest / 全源卡 / 指定 App 卡授权边界保持隔离。
- PHP 7.0、MySQL 5.7 继续作为发布兼容基线。
- `dylib()` 内部授权逻辑本版不重构，仅纳入 API 中心管理。

## 验证

- Phase 19.3 feature CI Run `35287755570`：SUCCESS。
- PHP 7.0：19.2 回归、19.3 API 中心、授权清空、在线更新包、2万 App 等价测试通过。
- MySQL 5.7：迁移重复执行两次通过，API 表与热路径索引通过。
- 正式 Release 流水线继续执行完整 PHP 7.0 回归、MySQL 5.7 迁移、ZIP/SHA256，并执行 `2026091802 -> 2026091803` GitHub Release 在线更新 E2E。

## 在线更新

- 正式版本：`2026091803`
- 基线：`2026091802`
- GitHub Release：`source-v2026091803`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
