# ZONOE 软件源 2026091918

## 基线

本版本以前一正式发布版本 `2026091917` 为基线。`2026091917` 及更早 Release Tag/Asset 保持不变；本次通过前滚版本完成 IPA 扫描模型收敛，不覆盖历史 Release。

## 更新内容

- IPA 扫描职责回归旧项目成熟模型：一次扫描生成一次 JSON snapshot，当前路径集合、new/changed/unchanged/missing 统计随扫描任务保存，不再依赖关系型 workset 标记驱动扫描状态。
- 扫描主链移除对 `fa_ipa_metadata.referenced` 与 `needs_reparse` 的硬依赖，避免生产数据库历史字段缺失导致整轮扫描在 `0 / total` 阶段失败。
- 不再把 `fa_ipa_scan_task_item` 作为扫描结果和治理缺失判断的必要数据源；Governance 的 IPA 缺失检测改为读取最近一次成功扫描的 JSON snapshot。
- Parser 保留持久 Worker、MD5 parse cache、Range 解析与 metadata 当前索引，但通过当前 metadata/fingerprint 处理扫描与解析并发，不依赖旧 workset 标记。
- IPA 后台列表、绑定统计与治理查询同步移除 `referenced=1` 过滤，避免扫描模型迁移后出现页面层残留依赖。
- 保留 2026091917 已验证的 Persistent Worker、在线更新、MySQL 5.7、OpenList HTTP E2E 与回滚链路。
- `UpdateIntegrity` 哨兵文件未修改，`file_sign` 保持 `8be29c04c34ba1d1cc7ec77d392b2bee`。

## 根因

2026091917 在生产扫描中暴露出数据库 Schema 与扫描代码不一致：扫描已发现 4816 个引用后，在 metadata workset 写入阶段因生产库缺少 `referenced` 等历史字段报 `fields not exists`，导致任务以 `scan_failed` 结束且进度停在 `0 / 4816`。进一步核对旧项目后确认，旧实现以 JSON task/cache/snapshot 记录扫描当前状态，并不要求每轮通过关系型字段维护 active workset。本版本因此将扫描职责收敛回 snapshot 模型，而不是继续扩大历史字段依赖。

## 发布验证

- 正式 Release 继续使用既有 `ZONOE Source Release` workflow，不修改历史发布流程和 Gate。
- PHP 7.0 regression、MySQL 5.7 migration、Phase 20 integration、HTTP load gate 必须成功。
- Persistent Worker contract 与 MySQL 5.7 migration 必须继续通过。
- `package-and-release` 必须成功生成全新的 `source-v2026091918`。
- `e2e-online-upgrade` 必须通过真实 GitHub Release 路径验证上一正式版本到 `2026091918` 的在线升级。

## 发布后验证

- 不移动、不覆盖 `source-v2026091917` 及更早 Tag/Asset。
- 生产环境升级后重新执行一次手动 IPA 扫描，确认不再依赖 `referenced` / `needs_reparse` 字段，并且任务可从 `0 / total` 正常推进。
- 确认扫描 JSON snapshot 能正确生成 current paths 与 new/changed/unchanged/missing 统计。
- 确认治理页面的 IPA 缺失判断来自最新成功扫描 snapshot，Parser/Worker 能继续消费待解析 metadata。
