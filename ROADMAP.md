# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable candidate branch: `feature/phase13-github-release`
- Phase: `13.6`
- Version: `2026091203`
- Commit: `d13ccb9ced56ca655a27cf13b5be0d6a33e724e2`
- Release: `source-v2026091203`
- Release CI: `34654867771` — SUCCESS
- 稳定边界：不改变 `appstore / appstore_v2` 公共协议、授权时长、UDID 格式、黑名单语义、卡密换绑剩余次数语义；保留 Nuosike 更新源。

## Phase 14 — Production Hardening / 生产稳定化

开发分支：`refactor/phase14-production-hardening`
基线：Phase 13.6 / `d13ccb9ced56ca655a27cf13b5be0d6a33e724e2`
当前已验证代码 Commit：`3600ceb25190ca93deb85689a94d44aea57c709d`
CI Run：`34663424209` — SUCCESS

### 14.1 更新事务、回滚和历史可靠性 — 已完成代码与 CI

- 多包连续升级从“单包回滚”提升为“整条更新链逆序回滚”。
- 当前失败包已创建备份时，也纳入 Manager 级全链回滚。
- 回滚文件复制、目录创建、新建文件删除失败不再被静默忽略。
- 更新下载和解压缓存由 `public/update/cache` 移到 `runtime/update/cache`，不再位于 Web Root。
- 修复 `UpdateRuntimeStore` 同秒历史记录排序不确定：历史文件加入单调微秒级排序键，确保刚产生的 rollback/update 记录稳定排在前面。
- 新增/扩展 `tests/phase14_update_atomicity_test.php`：覆盖第二包失败时回滚前一包、回滚 I/O 失败不得假成功、缓存目录不得位于 public、同秒历史顺序稳定。
- PHP 7.0 全回归通过。

### 14.2 真实生产升级/回滚闭环 — Next Task

验收链：

1. 从 `2026091202` 通过真实 GitHub Release 在线升级到 `2026091203`。
2. 验证版本、SHA256、文件覆盖、数据库、更新历史和备份记录。
3. 从成功更新记录回滚到 `2026091202`。
4. 验证程序、数据库、`ver.json`、`public/update/ver.txt` 和完整性状态全部恢复。
5. 再次在线升级到 `2026091203`，确认可重复闭环。
6. 做失败注入：SHA256 错误、ZIP 损坏、SQL 失败、文件不可写、备份失败、更新锁冲突和磁盘空间不足。

### 14.3 更新运维闭环

- 更新备份空间统计。
- 备份/历史保留策略和安全清理。
- 异常锁和中断任务诊断。
- 更新中心显示最近升级、最近回滚、当前完整性状态和 Release/Commit 对应关系。

## Phase 15 — 数据完整性与授权收尾

- 对生产 `fa_kami.kami` 做重复数据审计；只有审计和迁移方案确认后才考虑 UNIQUE INDEX。
- 明确 `/unbind` 的稳定语义：迁移“全部已激活历史卡”还是“仅当前有效授权行”。当前代码仅移动有效行，旧交接文档曾写全部历史，禁止在未确认前改变。
- 评估过期黑名单、授权事件、换绑日志的长期归档/保留策略。

## Phase 16 — 后台规模化与性能

- Authorization 主页 active blacklist 统计改为数据库条件查询，避免全表载入 PHP。
- transfers/events 从固定 300 行改为服务器分页。
- 优化 GitHubUpdateSource 检查路径，避免为所有历史 Release 逐个请求 SHA256。
- UpdateRuntimeStore 历史记录加入保留/索引机制；Phase14.1 已修同秒排序，但历史总量仍可能长期增长。
- 大数据库备份评估 OFFSET 扫描成本和更适合 BaoTa 的备份方式。

## Phase 17 — 选择性结构收口

只在有具体功能或 Bug 触发时做，不进行全仓库重写：

- 抽离 `BlacklistRepository/Service`，统一 App/Index/Transfer 的黑名单查询与首次命中写入。
- 抽离 `CardActivationService`，把 `App::activateCode()` 的事务编排移出控制器。
- 逐步减少 Controller 直接 DB orchestration。
- ThinkPHP 5.0.24 / FastAdmin 升级继续延期，直到 HTTP/数据库集成测试覆盖足够。

## 禁止破坏的稳定行为

- 不为重构修改公共软件源字段、锁定判断、加密包装格式或 Nuosike provider 协议。
- 不改变 day/week/month/quarter/year = 1/7/30/90/360 天。
- 卡密仍然一次性消费，授权时长仍可叠加。
- `transfer_count` 仍表示剩余换绑次数。
- BaoTa 包继续保留 `auto_install.json`、`import.sql`、`nginx.rewrite` 和 `BT_DB_*` 占位符。
- `App-mb.php` / `Index2.php` 不得恢复。
