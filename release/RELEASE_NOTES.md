# ZONOE 软件源 2026091920

## 基线

本版本以已正式发布并通过完整在线升级 E2E 的 `2026091919` 为冻结基线。`2026091919` 及更早 Release Tag/Asset 保持不变；本次以前滚版本收口后台异步执行、运行时健康检测和服务器迁移恢复。

## 修复：点击解析后后台被锁住

此前 Web 请求在 Persistent IPA Worker 离线时，会通过 `register_shutdown_function()` 在 PHP-FPM 请求结束阶段直接消费一个 queued job。虽然 `fastcgi_finish_request()` 能先把 HTTP 响应发给浏览器，但该 PHP 请求仍继续执行完整 IPA 解析，并继续持有当前后台 PHP Session 锁。因此同一管理员 Session 的刷新、筛选和其它 AJAX 请求都会等待解析结束，表现为“整个后台按钮都没反应”。

2026091920 删除这条 inline fallback。Web/FPM 现在始终只是 durable queue producer；扫描和解析只能由独立 Persistent IPA Worker 消费。Worker 离线时任务会留在队列，不会再把后台请求拖住。

## Persistent Worker 与迁移服务器恢复

- 新增 `scripts/install-ipa-worker-service.sh`，以 systemd 运行 `php think ipa:worker --sleep=2`，`Restart=always`、开机自启，使用非 root 服务账户。
- 新增 `scripts/zonoe-server-bootstrap.sh`。换服务器、恢复备份或忘记系统服务时，只需在站点根目录执行一次：

```bash
sudo bash scripts/zonoe-server-bootstrap.sh
```

该脚本会统一安装/恢复：

1. Persistent Parser Service
2. Persistent IPA Worker
3. systemd active 状态
4. Parser loopback RPC health

因此以后不需要单独记住 Parser Service 或 Worker 的安装步骤。

## 后台运行时健康检测

- 新增 `application/admin/controller/IpaRuntime.php`。
- 元数据页面打开时同时检查：
  - Persistent IPA Worker heartbeat
  - Persistent Parser Service RPC health
- 任一服务异常时，后台直接显示红色运行环境告警，并给出：

```bash
sudo bash scripts/zonoe-server-bootstrap.sh
```

Worker 离线时还会明确提示“队列任务不会被消费”，避免把环境故障误判为 IPA 文件或解析器故障。

## 刷新按钮

元数据页的刷新行为改为显式重新请求服务端数据，不再使用不可观察的静默刷新。界面会显示：

- 正在从服务器重新读取…
- 最后成功刷新时间
- 刷新失败、当前显示旧数据

点击“后台解析 1 个”后会短期轮询列表，便于直接看到 `pending → parsing → success/failed` 状态变化。

## 发布验证

- `phase20_worker_contract_test.php` 明确禁止生产 Worker 再出现 `register_shutdown_function` / `fastcgi_finish_request` inline job consumer。
- Persistent IPA Worker contract 与 MySQL 5.7 queue schema 通过。
- Phase 20 IPA Management contract、OpenList HTTP E2E、updater rollback、RC package 通过。
- Persistent Parser Service 与 `proc_open` 禁用 RPC 验证继续通过。
- Workset MySQL 5.7 与 Regression Checks 通过。
- 正式 `ZONOE Source Release` 必须完成 `package-and-release` 与真实 GitHub Release `e2e-online-upgrade`。

`UpdateIntegrity` 四个哨兵文件未修改，`file_sign` 保持 `8be29c04c34ba1d1cc7ec77d392b2bee`。
