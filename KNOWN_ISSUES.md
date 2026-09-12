# Known Issues and Refactor Backlog

## P0 — Phase 14.1 已修复

### 多包升级不是全链原子回滚
- 原实现每个 `UpdateInstaller` 只回滚当前失败包；如果前一个包已成功、后一个包失败，前一个包会残留。
- Phase14 分支由 `UpdateManager` 统一收集备份并按逆序恢复整条更新链。
- `tests/phase14_update_atomicity_test.php` 覆盖第二包失败场景。

### 回滚 I/O 失败可能被静默吞掉
- 原 `UpdateBackup::rollback()` 对文件恢复和新建文件删除使用 `@copy/@unlink`，不检查结果。
- Phase14 分支会收集文件/目录/删除错误，仍尝试数据库恢复，最后明确抛出不完整回滚错误。

### 更新临时文件位于 Web Root
- 原缓存位于 `public/update/cache`。
- Phase14 分支已迁移到 `runtime/update/cache`。

## P0 — 兼容性边界

- Runtime remains ThinkPHP 5.0.24 / FastAdmin-style；框架升级延期。
- 不改变 `appstore / appstore_v2` 公共协议和外部加密 provider/protocol。
- 不改变授权时长、一次性卡密消费、25/40 字符 UDID、`transfer_count` 剩余次数语义。
- 不恢复 `App-mb.php` / `Index2.php`。
- 不无计划迁移 `fa_category` 物理字段 `bt1a/bt1b/bt2a/bt2b`。

## P1 — 仍需处理

### `/unbind` 历史迁移语义不一致
- 当前 `CardDeviceTransfer::transfer()` 仅移动 `endtime > now` 的当前有效卡密行。
- 旧 HANDOFF/BUILD 曾写“全部已激活卡历史随新 UDID 迁移”。
- 当前合同测试只锁定有效行实现，并没有证明全部历史迁移。
- 在明确稳定语义和增加真实 DB fixture 前禁止修改。

### 黑名单持久化/查询重复
- `App.php` 与 `Index.php` 都包含 active blacklist 查询和首次命中 `usetime` 写入。
- `CardDeviceTransfer` 也直接查询 `fa_black`。
- 后续候选：`BlacklistRepository/BlacklistService`，但必须先补 DB-backed equivalence test。

### Card activation orchestration 仍在 Controller
- `App::activateCode()` 同时负责锁行、叠加授权、quota、写卡密、事件日志和事务。
- 后续候选：`CardActivationService`；当前不为“整洁”而拆。

### Authorization 后台规模化
- 首页 active blacklist 目前全表读入 PHP 再统计，应改为 SQL 条件统计。
- transfers/events 当前固定最多 300 行，应改服务器分页。

### GitHub 更新检查 N+1
- `GitHubUpdateSource::releasePackages()` 会对每个符合条件的 Release 再请求一次 SHA256 asset。
- 更新检查只需要最新相关 Release，可在不改变选择语义前提下减少 SHA 请求。

### UpdateRuntimeStore 长期增长
- status/history 使用 runtime JSON 文件。
- history 没有保留策略；`historyById()` 线性扫描文件。
- Phase14.3 应增加安全清理/索引机制。

### Card DB uniqueness 仅应用层保证
- Card generation 使用 `random_bytes` 并查询现有 `fa_kami.kami`。
- 数据库仍无 UNIQUE INDEX；历史重复数据未做生产审计。
- 必须先审计、迁移、回滚方案，再加唯一索引。

### 黑名单历史增长
- 过期黑名单保留用于审计，运行时忽略。
- 尚无归档/清理策略。

### 大数据库 PHP 备份成本
- `UpdateBackup` 对各表使用 500 行分批 + OFFSET 扫描。
- 大表下 OFFSET 可能变慢；需要结合 BaoTa/MySQL 实际数据量评估替代方案。

## P2 — 未来维护

### Framework-to-service separation
- 已抽离 Source payload/config/response、Policy、Authorization、Updater 等核心组件。
- Controllers 仍存在直接 DB orchestration，但只在具体 Bug/功能触发且有测试时继续抽离。

### Security headers / cookies
- 框架级 transport/cookie defaults 仍为 legacy。
- 按部署环境单独强化，不在现阶段全局改变默认行为。

### Physical Category schema migration
- `SourceAppRecord` 已提供语义层。
- 物理改名当前没有用户价值，迁移风险大，继续延期。

## 已完成的重要清理

- Phase 9 删除 `App-mb.php` / `Index2.php` 并由 CI 防止回归。
- Category 使用服务器分页、数据库搜索、轻量字段、add/edit 才构建完整父树。
- `CategoryDailyStat` 使用 `YYYYMMDD` + transaction/row lock。
- Card generation 改为 cryptographic random；授权可叠加但单卡仍一次使用。
- `SourceAppRecord` 隔离 legacy Category 物理字段。
- `SourceResponse` / `SourceHttpClient` 收口公开源输出与外部加密 HTTP。
- Nuosike/GitHub updater 共用 hardened pipeline，GitHub 强制 HTTPS + SHA256。

## 性能已知事实

历史实测：plain `/appstore` 总时间约 `0.11s`；encrypted `/appstore` 约 `10.48s`。主要耗时仍来自外部整包加密服务，而不是本地 payload mapping。除非更换协议/provider，否则不要把本地微优化当成该 10s 延迟的根治方案。
