# ZONOE 软件源 2026091913

## 基线

本版本以已发布的 `2026091912` 为直接基线。`2026091912` 已完成 package-and-release，但最终 `e2e-online-upgrade` 因发布说明契约过窄而失败；因此 1912 冻结，不再覆盖，后续修复进入新版本 `2026091913`。

## 更新内容

- 修正 Phase 20 UI 契约测试对具体版本号的硬编码，使后续 `1914 / 1915 / ...` 不再因升版本身失败。
- 保持 1912 已完成的 OpenList MySQL 持久化、IPA MySQL source/metadata、引用目录发现、目录缓存、扫描分页和 Phase 20 migration payload。
- 保持 FastAdmin PATH_INFO/action、Range Parser、绑定、治理、恢复、Retention 与 appstore 协议语义不变。
- 发布版本继续严格单调递增；已发布版本不复用、不覆盖 Release Asset。

## 1912 已验证结果

`2026091912` 的 ZONOE Source Release #159 已通过：

- PHP 7.0 full regression。
- MySQL 5.7 migrations。
- Phase 19.3.1 HTTP load gate。
- Phase 20 OpenList HTTP E2E。
- Phase 20 updater rollback E2E。
- package-and-release。

最终 `e2e-online-upgrade` 失败原因仅为 Release Notes 契约要求精确包含“更新内容”字符串，而 1912 使用了“主要更新”标题。

## 发布后验证

- GitHub Release 应创建 `source-v2026091913`，不得覆盖 `source-v2026091912`。
- 在线升级 E2E 应解析上一版本为 `2026091912`，目标版本为 `2026091913`。
- 真实服务器当前若仍为 `2026091911`，应检测到最新 `2026091913`，并在受控验证后执行升级。
