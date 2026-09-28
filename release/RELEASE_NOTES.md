# ZONOE 软件源 2026092419

## 更新内容

### IPA 资产：改用卡密同款真实搜索

本版本以 `source-v2026092418` 为升级基线，重做“总览 -> IPA 资产”的搜索/筛选交互，改为与卡密列表一致的 FastAdmin `Table.api.bindevent()` + `searchList` + 服务端 `search/filter/op` 查询契约。

- 新增 `IpaAssets` 专用服务端查询端点，不再依赖表格前端伪过滤。
- 保留右上角快速搜索，同时增加卡密同款字段筛选。
- 支持按 ID、OpenList 来源、IPA 文件名、Bundle ID、App 名、Version、Build、解析状态筛选。
- “异常”列提供真实筛选：异常、正常、待比对、字段异常、未匹配、源错误。
- 快速搜索输入“异常”时，直接查询 `ipa_compare_result` 中 `anomaly / unmatched / source_error` 的资产。
- 快速搜索输入“未匹配 / 源错误 / 正常 / 待比对”同样映射真实比对结果，不再把这些词当普通文件名搜索。
- OpenList 来源名称通过服务端映射到 `source_id` 后过滤。
- `total` 和 `rows` 都基于过滤后的数据库查询，保持 server-side pagination。

### 扫描任务：仅展示

- 扫描任务表显式关闭 `search` 和 `commonSearch`。
- 保留分页、状态、计数、Worker、时间等展示。
- 不改变扫描任务执行、清理或 Worker 逻辑。

### 兼容性

2419 不改变：

- OpenList 数据源表结构；
- IPA 扫描/解析主链；
- IPA 比对/写回数据结构；
- Dylib Protocol v1/v2；
- 卡密/授权现有业务协议；
- GitHub 在线更新 SHA256、备份、数据库迁移与失败回滚安全链。

目标升级路径：`source-v2026092418 -> source-v2026092419`。
