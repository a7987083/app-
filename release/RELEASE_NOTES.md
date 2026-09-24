# ZONOE 软件源 2026092401

## 更新内容

本版本以 `source-v2026092207` 为升级基线，修复 IPA Data Center 在真实宝塔生产环境中“扫描任务创建成功但长期停留 pending”的 Worker 运行链问题。该版本继续沿用现有在线更新协议，不新增数据库迁移。

### 扫描 Worker 自动启动

- 后台创建全量/增量扫描任务后检查 Scan Worker 是否存活。
- Worker 不在时，由当前站点 PHP-FPM 环境自动拉起扫描 Worker。
- 使用 `PHP_BINDIR/php` 作为 CLI，确保与站点 PHP-FPM 使用同一 PHP 安装，避免 `/usr/bin/php` 指向 PHP 8.x 而旧 ThinkPHP 需要 PHP 7.0 的兼容问题。
- 子进程继承 FPM 环境变量，包括生产现有 `PHP_IPA_SERVER_SECRET`，可继续解密既有 OpenList `token_ciphertext`。
- 启动前写入 `starting` 心跳，降低连续点击导致重复拉起 Worker 的概率。

### 兼容性与验证

- PHP 7.0 语法/运行兼容检查通过。
- MySQL 5.7 schema、worker claim contention、100k scale integration 通过。
- 扫描安全、管理/内存安全、运维控制和回归检查通过。
- 本版本无新增 SQL，继续沿用 2026092205 数据库结构。

### 在线更新

继续沿用现有 `UpdateManager / UpdateInstaller`：ZIP 下载、SHA256 校验、备份、既有增量 SQL、文件覆盖、文件校验、版本写入和失败回滚逻辑均不改变。

目标升级路径：`source-v2026092207 -> source-v2026092401`。
