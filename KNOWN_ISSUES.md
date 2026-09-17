# Known Issues and Refactor Backlog

## P0 — 稳定兼容边界

- Runtime remains ThinkPHP 5.0.24 / FastAdmin-style；框架升级延期。
- 不改变旧 `appstore / appstore_v2` 公共字段、URL 和加密 envelope。
- V3 只有 `supported=0` 才允许 fallback；授权、黑名单等业务拒绝必须保持 `supported=1`。
- 不改变卡密一次性消费、授权时长叠加、25/40 字符 UDID 和 `transfer_count` 剩余次数语义。
- 不恢复 `App-mb.php` / `Index2.php`。
- 不无计划迁移 `fa_category` 物理字段 `bt1a/bt1b/bt2a/bt2b`。

## P1 — Phase 19 客户端尚不在本仓库

- `app-` 是服务端仓库；代码检索未发现 Objective-C 或 `sqlite3` 客户端实现。
- `2026091801` 已完成并验证服务端 snapshot/delta/reset 契约，但不能据此宣称 iOS 客户端 SQLite 已实机接入。
- 下一步必须在实际软件源浏览客户端仓库实现 transactional SQLite、full sync、delta、fallback 和真机 E2E。

## P1 — V3 change-log retention 尚未实施

- Phase19.1 已提供 `min_delta_since/min_since` 和 `history_gap`，因此协议已经支持未来安全清理旧 revision。
- 当前尚未实施生产 retention/GC 策略；`fa_source_change` 长期增长需要在 Phase20 根据实际数据量制定保留窗口、索引和监控。
- 清理时必须保证 `min_since = first_retained_revision - 1` 的可续接语义。

## P1 — `/unbind` 历史迁移语义不一致

- 当前 `CardDeviceTransfer::transfer()` 只移动仍有效的授权行。
- 旧文档曾写“全部已激活历史随新 UDID 迁移”。
- 在明确稳定语义并加入真实 DB fixture 前禁止改变行为。

## P1 — 黑名单持久化/查询重复

- `App.php`、`Index.php` 与 `CardDeviceTransfer` 仍有重复 DB orchestration。
- 后续可抽 `BlacklistRepository/Service`，但必须先有 DB-backed equivalence test。

## P1 — Card activation orchestration 仍在 Controller

- `App::activateCode()` 仍负责锁行、叠加、quota、事件日志和 transaction。
- 候选 `CardActivationService` 只在有行为测试保护时拆分。

## P1 — 后台/更新规模化

- Authorization active blacklist 统计仍有全表 PHP 计数路径。
- transfers/events 仍存在固定行数而非完整服务器分页的历史路径。
- `GitHubUpdateSource` 检查历史 Release 时仍可能产生额外 SHA asset 请求。
- `UpdateRuntimeStore` 历史文件长期增长仍需 retention/index。
- 大数据库备份使用 LIMIT/OFFSET，需基于生产数据量评估。

## P1 — Card DB uniqueness

- `fa_kami.kami` 唯一性仍主要由应用层生成/查询保证。
- 加 UNIQUE INDEX 前必须先生产重复审计、迁移和回滚方案。

## 已验证完成

- Phase 9 删除旧 duplicate controllers 并由 CI 防回归。
- GitHub/Nuosike updater 共用 hardened pipeline；GitHub 强制 SHA256。
- Phase14 更新全链回滚、私有 cache、历史顺序问题已修。
- Phase18 V3 revision/page/delta、开关和 fallback 已发布。
- Phase19.1 snapshot consistency、delta retention boundary/reset 协议已发布。
- `2026091714 -> 2026091801` GitHub Release 在线更新 E2E 已通过。

## 性能已知事实

历史实测 plain `/appstore` 约 `0.11s`，encrypted `/appstore` 曾约 `10.48s`；主要耗时来自外部整包加密服务。除非更换 provider/protocol，不应把本地微优化当作该延迟的根治方案。
