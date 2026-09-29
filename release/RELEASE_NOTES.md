# ZONOE 软件源 2026092420

## 更新内容

### IPA 资产搜索后端执行修复

本版本以 `source-v2026092419` 为升级基线，只处理“总览 -> IPA 资产”的搜索执行链和对应回归，不带回已删除 2420～2427 分支中的其他改动。

- 修复使用 IPA 资产搜索后，后端异常返回 HTML 错误页，BootstrapTable 因收不到 `total/rows` JSON 而提示“未知的数据格式!”的问题。
- `count()` 与列表 `select()` 分别从相同搜索参数重新构造 Query Builder，不再 clone/复用带 PDO bindings 的查询对象。
- FastAdmin commonSearch 有有效 `filter` 时优先执行字段筛选；只有 commonSearch 为空时才执行右上角 quick search，避免旧搜索词与新筛选条件继续 AND。
- 单值等值条件使用显式 `where(field, '=', value)`。
- 新增 PHP 7.0 + MySQL 5.7 真实数据库回归，覆盖 IPA 文件名、Bundle ID、App、Version、Build、数字 ID、OpenList 来源、中文解析状态、异常关键字及字段筛选。
- 新增浏览器参数语义回归：即使 quick search 残留无关关键字，`filter.status=discovered` 等 commonSearch 仍必须按字段筛选返回正确结果。
- 保持 BootstrapTable `total/rows` 响应结构和 server-side pagination 不变。

## 不变范围

2420 不改变：

- OpenList 数据源结构；
- IPA 扫描、解析和 Worker 主链；
- IPA 比对与写回业务；
- Dylib Protocol v1/v2；
- 卡密/授权业务协议；
- Dylib 版本管理业务；
- 在线更新 SHA256、备份、数据库迁移与失败回滚安全链。

目标升级路径：`source-v2026092419 -> source-v2026092420`。
