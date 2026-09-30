# ZONOE 软件源 2026092425

## 更新内容

### IPA 数据中心：总览模块第一阶段重构

本版本以 `source-v2026092424` 为直接基线，仅重构 IPA 数据中心“总览”页面的数据聚合层，保持页面字段、排序和统计语义不变。

- 新增 `IpaOverviewService`，将总览统计和数据源列表查询从 `IpaCenter::index()` 中拆出，控制器只负责读取解析设置、调用服务并向视图赋值。
- 将 `ipa_asset` 与 `ipa_scan_item` 原先按状态逐项执行的多次 `COUNT` 查询改为分别按 `status GROUP BY` 聚合，减少总览页数据库往返次数。
- 保留原有返回契约：`sources`、`assets`、`pending`、`failed`、`parse_pending`、`parse_failed`、`dylibs`、`verify24h` 字段不变；数据源仍按 `id desc` 排序。
- `verify24h` 仍按 `created_at >= now - 86400` 计算，24 小时边界保持包含语义。
- 新增真实 MySQL 回归测试，以 2424 控制器原算法为基准逐字段比较新实现，并覆盖正常数据、空数据、24 小时边界和数据源排序。
- 新增 `IPA Overview 2425 CI` 专项工作流；重构代码本身已通过专项回归。
- 在线更新清单加入 `application/common/library/Ipa/IpaOverviewService.php`，确保控制器与新增服务在同一更新包内交付，避免只更新控制器导致类缺失。

## 基线说明

2425 的直接开发基线：

`source-v2026092424` / `e5853e3f891443b9581a7cee3741e19ce21a2e03`

## 不变范围

2425 不改变：

- IPA 扫描、解析 Worker 与 2424 的远程解析事务边界修复；
- IPA 资产搜索、删除、清空解析结果等业务语义；
- OpenList 数据源协议；
- Dylib Protocol v1/v2；
- 卡密/授权协议；
- 在线更新 SHA256、备份、数据库迁移与失败回滚安全链。

目标升级路径：`source-v2026092424 -> source-v2026092425`。

## 真机验证

CI 用于证明数据契约与数据库行为未改变；后台页面的真实部署/真机访问验证仍作为发布后的独立验收步骤，不以 CI 代替真机验证。
