# ZONOE 软件源 2026092421

## 更新内容

### Dylib 验证中心：版本管理编辑稳定性修复

本版本以 `source-v2026092420` 为升级基线，只处理“Dylib 验证中心 -> 版本管理 -> 编辑”触发的版本列表/验证日志 500 与多余刷新问题。

- 修复点击版本管理中的“编辑”后，`versions` 与 `logs` XHR 返回 500，页面出现“你所浏览的页面暂时无法访问”的问题。
- `versions()` 的 `count()` 与列表 `select()` 使用独立 Query Builder，不再 clone/复用带绑定参数的查询对象。
- `logs()` 同样分离 count/list Query Builder，避免带 `dylib_key`、`result_code` 等过滤条件时出现 PDO/Query 状态复用异常。
- `dylib_id`、`dylib_key`、`result_code` 等精确过滤统一使用显式 `where(field, '=', value)`。
- 修正版本编辑前端行为：仅当被编辑记录所属 Dylib 与当前上下文不一致时才同步 Dylib；正常编辑当前 Dylib 的版本记录不再无条件刷新版本表和验证日志表。
- 新增 PHP 7.0、JavaScript 语法、查询结构与版本编辑 UI 行为契约回归。
- 新增 MySQL 5.7 真实端点回归，覆盖 `versions?dylib_id=5`、`logs?dylib_key=ceshi` 及日志结果码过滤，验证返回 BootstrapTable `total/rows` JSON。

## 不变范围

2421 不改变：

- IPA 资产搜索、扫描、解析与比对主链；
- OpenList 数据源结构；
- Dylib Protocol v1/v2；
- 卡密/授权协议；
- 版本保存字段和业务语义；
- 在线更新 SHA256、备份、数据库迁移与失败回滚安全链。

目标升级路径：`source-v2026092420 -> source-v2026092421`。
