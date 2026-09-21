# ZONOE 软件源 2026091919

## 基线

本版本以已正式发布并通过完整在线升级 E2E 的 `2026091918` 为冻结基线。`2026091918` 及更早 Release Tag/Asset 保持不变；本次以前滚版本替换 IPA Parser 的进程模型，不修改历史 Release。

## 更新内容

- IPA Parser 从 PHP `proc_open()` 每个 IPA 启动一次 Python，迁移为独立常驻的 **Persistent Parser Service**。
- PHP `IpaParserRunner` 只通过本机 `tcp://127.0.0.1:19191` RPC 与 Parser Service 通讯，生产代码不再依赖 `proc_open`、`exec`、`shell_exec`、`system`、`popen`。
- 继续复用成熟的 `scripts/ipa-range-info.py`，HTTP Range ZIP、binary/XML plist、Bundle metadata、Mach-O 架构、图标、embedded.mobileprovision、Framework/Dylib/Extension 等解析逻辑保持原实现；`PARSER_VERSION` 保持 1，现有 MD5 parse cache 不失效。
- 新增 `scripts/ipa-parser-service.py`：仅允许 IPv4 loopback，使用 newline-delimited JSON RPC，常驻进程复用 Python 解析运行时。
- Parser Service 会监控自身及 `ipa-range-info.py` 的文件签名；在线更新替换文件后，下一个请求触发 `os.execv` 自刷新。PHP RPC Client 对自刷新连接窗口自动重试一次，因此后续 parser 文件在线更新无需 PHP 重新获得进程创建权限。
- 新增 `scripts/install-ipa-parser-service.sh`：一次性创建并启用 systemd `zonoe-ipa-parser.service`，以非 root 账户运行，`Restart=always`、开机自启，并启用基础 systemd sandbox。
- 在线更新 ZIP 已包含 Persistent Parser Service 与 systemd 安装器。Web 在线更新本身不获取 root/systemd 权限，因此首次升级本版本后需要在服务器站点根目录执行一次 `sudo bash scripts/install-ipa-parser-service.sh`。
- Parser 正式 contract 增加生产环境回归：启动常驻 Parser Service 后，再以 `disable_functions=proc_open,exec,shell_exec,system,passthru,popen` 的 PHP 7.0 子进程连接 RPC，必须成功。
- 2026091918 的 JSON scan snapshot、Persistent IPA Worker、MySQL 5.7、OpenList HTTP E2E、rollback 与历史发布流程全部保留。
- `UpdateIntegrity` 四个哨兵文件未修改，`file_sign` 保持 `8be29c04c34ba1d1cc7ec77d392b2bee`。

## 根因

生产服务器完成 2026091918 扫描修复后，真实 IPA 解析暴露 `PHP proc_open is required for IPA parser worker`。核对当前代码与旧项目后确认：旧项目同样通过子进程调用 Python Range Parser，只是旧 Node 服务使用 `child_process.spawn()`，当前 PHP 迁移版使用 `proc_open()`。生产宝塔 PHP 禁用了 `proc_open`，因此任何未命中 MD5 parse cache 的 IPA 都会在解析器启动前失败。长期方案不是继续扩大 PHP `disable_functions` 权限，而是把已经成熟且职责独立的 Python IPA Parser 固化为常驻服务。

## 发布验证

- PHP 7.0 parser/worker/scan contract 必须通过。
- Parser contract 必须证明 production `IpaParserRunner.php` 不包含 PHP process-spawn 依赖。
- 在禁用 `proc_open/exec/shell_exec/system/passthru/popen` 的 PHP 7.0 进程中，Persistent Parser RPC health 必须通过。
- `ipa-range-info.py --self-test` 与 Persistent Parser Service self-test 必须通过。
- Phase 20 Integration、OpenList HTTP E2E、updater automatic rollback、legacy rollback、MySQL 5.7、Persistent Worker contract 必须继续通过。
- 在线更新包必须包含 `scripts/ipa-parser-service.py` 与 `scripts/install-ipa-parser-service.sh`。
- 正式 `ZONOE Source Release` 必须完成 `package-and-release` 与真实 GitHub Release `e2e-online-upgrade`。

## 发布后操作

在线更新至 `2026091919` 后，仅首次需要以 root 安装系统服务：

```bash
cd <站点根目录>
sudo bash scripts/install-ipa-parser-service.sh
systemctl status zonoe-ipa-parser.service --no-pager
```

安装成功后 PHP-FPM/PHP CLI 都无需开放 `proc_open`。此前因 `proc_open` 缺失写成 `failed` 的 IPA 可重新置为 `pending` 或等待现有失败重试机制重新领取。
