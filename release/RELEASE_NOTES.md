# ZONOE 软件源 2026091912

## 基线

本版本以已实际发布并被服务器安装过的 `2026091911` 为历史基线。原始 `2026091911` 发布 HEAD 为 `edcd4d0c7cf50726ce7d09e3075fe23506ea7fe5`。从该基线到本次候选 HEAD 共继续推进 19 个提交。

## 主要更新

- OpenList 配置正式转为 MySQL 持久化，避免继续把 runtime JSON 作为主持久层。
- 新增 IPA MySQL source/metadata 管理、引用目录发现、目录缓存与相关后台入口。
- 改进 OpenList 引用目录扫描和分页，完善远端文件元数据处理。
- 在线更新包继续包含 Phase 20 有序 MySQL migration payload，并修正对应 RC/PHP 7 契约测试。
- 保留既有 FastAdmin PATH_INFO/action、Range Parser、绑定、治理、恢复、Retention 与 appstore 协议语义。

## 发布版本规则修正

`2026091911` 已经在真实服务器执行过在线更新，因此从本版本开始将已发布版本视为不可变历史版本。任何后续代码、测试、打包或发布内容变化都必须使用新的递增版本号，不再以相同 Tag/版本号覆盖旧 Release Asset。

当前顺序：

`2026091911` → `2026091912` → `2026091913` → ...

## 已有验证

在 2026091912 升版前的候选 HEAD `5e72bc30b9bb714f69e79597ca3c0d3704cfd346` 上：

- PHP 7.0 full regression：PASS。
- MySQL 5.7 migrations：PASS。
- Phase 20 OpenList HTTP E2E：PASS。
- Phase 20 updater rollback E2E：PASS。
- Phase 19.3.1 HTTP load gate：PASS。
- GitHub Release package：PASS。
- Real GitHub Release online-update E2E：PASS。

本次 `2026091912` 版本提交后仍需重新执行完整 CI，以上结果不能替代新版本提交的最终 CI。

## 发布后验证

- 从真实服务器 `2026091911` 检测到 `2026091912`。
- 下载并校验新的 `zonoe-online-update.zip` SHA256。
- 完成 program 覆盖与 MySQL migration。
- 验证 OpenList 配置保存/读取、测试连接、扫描、metadata、绑定、治理和更新历史。
