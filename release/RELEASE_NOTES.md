# ZONOE 软件源 2026092424

## 更新内容

### IPA Parser P0：远程解析与数据库事务解耦

本版本以 `source-v2026092423` 为直接基线，修复 IPA 远程解析链的 P0 结构性问题。

- 将 Framework / Dylib / 主可执行文件的远程 HTTP Range、ZIP 解压前缀读取、Mach-O 元数据提取与可选 SHA256 计算全部移动到数据库事务开始之前。
- `Db::startTrans()` 之后只执行 `ipa_asset`、`ipa_binary`、`ipa_app_identity` 的数据库更新/删除/插入，不再在事务内触发远程网络请求。
- 避免 IPA 包含较多 Framework / Dylib 时，长时间持有数据库事务并串行等待远程 Range 请求，降低 Worker 卡住、事务占用过久及并发受阻风险。
- 新增 IPA Parser 事务边界回归门禁，明确校验远程 enrichment 必须在 `Db::startTrans()` 之前完成，并禁止事务体重新出现 ZIP/Range 提取调用。
- 解析时优先使用 OpenList 当前返回的文件大小，避免同一路径 IPA 被覆盖后继续使用 `ipa_asset.size_bytes` 旧值；解析成功后同步回写最新大小。
- P1 项（ZIP64 支持、HTTP 206 兼容策略等）不纳入本版本，不阻塞 2424 发布。

## 基线说明

2424 的直接开发基线：

`source-v2026092423` / `2928239dee7267cdd7311e760e9eada8920047b4`

P0 主修复提交：

`eaf2f30a42160f99b73e5d1ac3c44922d356d020`

## 不变范围

2424 不改变：

- IPA 扫描发现与资产搜索业务语义；
- OpenList 数据源协议；
- Dylib Protocol v1/v2；
- 卡密/授权协议；
- 在线更新 SHA256、备份、数据库迁移与失败回滚安全链。

目标升级路径：`source-v2026092423 -> source-v2026092424`。
