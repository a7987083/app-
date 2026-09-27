# ZONOE 软件源 2026092417

## 更新内容

### GitHub 在线更新性能优化

本版本以 `source-v2026092416` 为升级基线，优化后台“GitHub 在线更新”的 Release 查询与 SHA256 获取路径，不降低更新完整性校验、备份或回滚要求。

- GitHub Release 列表仍一次获取稳定 Release 元数据，但不再为最多 30 个历史 Release 逐个下载 `.sha256`。
- 优先直接使用 GitHub Release asset 的 `digest=sha256:...`，可用时检查最新版只需要一次 Release API 请求。
- 当 asset digest 不可用时，先按本地版本过滤真正需要安装的 Release，再仅为这些候选版本请求 companion `.sha256`。
- 保留更新包 SHA256 校验、安全解压、更新前备份、数据库迁移、文件覆盖校验和失败回滚。
- 将 `UpdateHttpClient.php` 与 `GitHubUpdateSource.php` 纳入在线更新包白名单，确保旧版本通过在线更新即可获得本次性能修复。
- 增加 updater contract 回归：验证 asset digest 单请求路径，以及历史 Release 不再产生无意义 SHA256 请求。

### 兼容性

2417 不改变：

- Dylib Protocol v1 / v2 canonical；
- Dylib 在线验证 wire contract；
- Runtime Config 签名；
- 卡密/授权现有业务协议；
- OpenList Token 连接协议；
- 现有更新包 SHA256、备份、数据库迁移与回滚安全链。

目标升级路径：`source-v2026092416 -> source-v2026092417`。
