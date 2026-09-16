# ZONOE 软件源 2026091609

## 更新内容

- 修复宝塔/PHP `open_basedir` 环境下高速扫描引擎初始化失败的问题。
- 根因是 1607/1608 在检测 PHP CLI 时，对 `PHP_BINDIR/php` 使用 `is_file()` / `is_executable()`；当宝塔将站点 PHP-FPM 的 `open_basedir` 限制为网站目录与 `/tmp` 时，`/www/server/php/70/bin/php` 位于白名单之外，会直接触发 `open_basedir restriction in effect`。
- 新增 `FastStorageManagerCompat`：不再通过 PHP 文件系统函数探测 `PHP_BINDIR`，改为通过受控的 shell 执行探针验证 CLI 是否可用，从而避免跨出站点 `open_basedir` 白名单。
- 优先尝试当前 `PHP_BINDIR/php`，失败时回退 PATH 中的 `php`；后台 worker 继续只执行固定服务端脚本和固定站点根路径，不接受前端任意命令或任意扫描路径。
- 运维控制器统一切换到 open_basedir-safe 兼容层，扫描、自动安全清理、人工删除与状态读取均使用同一能力检测逻辑。
- 新增回归契约：禁止在兼容层中再次对 `PHP_BINDIR` 使用 `is_file()` / `is_executable()`，防止同类问题回归。
- 保留 GNU find 高速扫描、源码目录指纹、持久索引、扫描进度条和自动安全清理进度条。

## 兼容性

- 目标环境继续兼容宝塔 PHP 7.0。
- 不要求关闭 `open_basedir`，也不要求把 `/www/server/php/70/bin` 加入站点白名单。
- 不修改数据库结构，不改变授权、卡密、黑名单、换绑、appstore/appstore_v2 与 Nuosike 兼容逻辑。
- GitHub 在线更新继续使用 HTTPS、SHA256、备份、文件校验与回滚。
