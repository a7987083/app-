# ZONOE 软件源 2026092428

## 更新内容

### IPA Parser：修复 PHP-FPM 下 CLI Worker 启动失败

本版本以 `source-v2026092427` 为在线升级基线，仅修复 IPA Parser 的启动链，不改动 2427 已完成的 Parser V2 metadata / Range / MD5 / OpenList 解析模型。

2026092427 的 `IpaWorkerLauncher::ensureParseWorker()` 会在 PHP-FPM 请求里直接使用 `PHP_BINARY` 启动 `php think ipa:parse-worker --scheduled`。在宝塔等 PHP-FPM 环境中，`PHP_BINARY` 可能指向 `.../sbin/php-fpm`，而不是 `.../bin/php`，因此后台虽然能拿到一个启动 PID，但 Parser CLI 实际可能立即退出，表现为“开启解析后仍没有解析”。

2026092428 改为明确解析 CLI PHP：

- 当前 PHP 安装目录的 `bin/php`；
- `PHP_BINDIR/php`；
- 宝塔版本化路径 `/www/server/php/<major><minor>/bin/php`；
- `/usr/bin/php`；
- `/usr/local/bin/php`。

找不到 CLI PHP 时返回 `cli_php_not_found`，不再继续使用 php-fpm 二进制冒充 CLI Worker。

### 保持 2427 Parser V2 行为

- 扫描完成后应用内触发 Parser，不要求 systemd timer 才能解析；
- Parser 仍在独立 CLI PHP 进程执行，不占用 PHP-FPM shutdown worker；
- systemd service/timer 仍作为可选运维入口；
- OpenList MD5 内容指纹与相同 MD5 metadata 复用保持不变；
- 30 分钟 OpenList 目录缓存与全量扫描强制 refresh 保持不变；
- 256 KiB Range block cache 与单 IPA 16 MiB 网络预算保持不变；
- Info.plist-only metadata 路径保持不变；
- `ipa:parse-worker --scheduled` 有界批次保持不变；
- `parse_failed` 30 分钟 cooldown 与孤立 parsing 回收保持不变；
- “清空解析结果”仍只清解析派生数据，不删除 IPA 扫描/发现记录。

## CI / 验证

原修复提交 `a90861d3135b1c87972d0998182a9a6233b6ca76` 已通过 `IPA Parser V2 2427 Migration CI` 与 `Auto Online Release Gate`。本 2026092428 发布会重新执行完整 sibling CI、canonical Release 与真实 GitHub Release 在线升级 E2E。

CI / E2E 通过仅证明代码与升级链满足契约；生产服务器仍需验证 `discovered -> parsing -> parsed` 的真实状态变化。

## 升级路径

`source-v2026092427 -> source-v2026092428`
