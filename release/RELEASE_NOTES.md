# ZONOE 软件源 2026092432

## 更新内容

### `/unbind` UDID 输入改为 1-128 字符

- 移除旧的 25 位 / 40 位固定长度限制。
- 旧 UDID、新 UDID、`/unbind/query` 查询 UDID 统一只要求非空且不超过 128 个字符。
- 不再要求固定 UUID/UDID 格式，也不限制必须为某一种字符组合。
- 仍保留“新旧 UDID 不能相同”、卡密与旧 UDID 匹配、黑名单、换绑额度、每日限制、冷却和 IP 频率限制等原业务规则。

### 数据库兼容

新增 `2026092432_unbind_udid_length.sql`：

- 当 `fa_kami.udid` 长度小于 128 时自动扩到 `varchar(128)`；
- 尽量保留原 charset、collation、nullable、default 和 comment；
- MySQL 5.7 可重复执行；
- 如果生产库已经大于 128（例如 255），不会降窄。

### 页面

`https://app3.zonoeios.xyz/unbind` 的旧 UDID、新 UDID以及查询 UDID 输入框统一标明支持 1-128 字符；前端最大长度与后端约束一致。

### 兼容性

- 基于正式 `source-v2026092431` 开发。
- 保留 2431 的验证记录 UDID/IP、offline_grace 继承能力。
- 保留 2430 Secretless Auth v3、Challenge、Device Key、RSA Runtime Config 签名及原有 API/后台功能。

## CI / 验证

`Unbind UDID 2432 CI` 覆盖：

- PHP 7.0 syntax；
- 1、25、36、40、128 字符输入必须通过；
- 空值和 129 字符必须拒绝；
- 不再存在 25/40 位固定长度判断；
- MySQL 5.7 将 legacy `varchar(40)` 幂等扩到 128；
- 扩容后 128 字符可完整写入且历史数据/列元信息保持；
- 已经为 `varchar(255)` 的生产式 schema 不被降窄；
- 在线更新 ZIP 包含 `CardDeviceTransfer.php`、`unbind.html` 和 2432 migration。

正式发布继续经过 canonical Release gate：完整 PHP regression、source integrity、真实 MySQL 5.7 migration、HTTP 并发 gate 和真实 GitHub Release 在线升级 E2E。

## 升级路径

`source-v2026092431 -> source-v2026092432`
