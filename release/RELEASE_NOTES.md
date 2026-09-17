# ZONOE 软件源 2026091711

## 更新内容

### 在 2026091710 稳定基线上恢复 1707/1708 性能功能

- 继续保留 2026091710 对 `/appstore` 的 schema-adaptive 修复：`fa_category.renewal_entry` 缺失时自动排除该字段，软件源接口不能再因漏跑 1704 迁移整体报错。
- 恢复 2026091707 的 legacy `appstore` bkey 本地缓存：默认 fresh TTL 900 秒、stale fallback 86400 秒，减少每次请求远程读取 `update.json/key.json` 的固定等待。
- 恢复公共 App 行数据 15 秒共享缓存，减少高并发添加/刷新时重复扫描 `fa_category`。
- 恢复 2026091708 的 cache fail-open：缓存读、写、删除异常只记录日志，始终回退数据库，缓存故障不能影响 `/appstore` 可用性。
- 恢复 `appstore/appstore_v2` HTTP gzip 传输压缩；仅客户端声明 `Accept-Encoding: gzip` 时启用，HTTP 解压后的协议正文保持不变。
- 恢复 `[SourcePerf]` 慢请求性能日志，记录 App 数量、数据来源、JSON/加密/总耗时、响应字节和峰值内存。
- 恢复 1k/5k/10k/20k/50k 双协议基准工具及相关兼容测试。
- 不修改 `appstore` / `appstore_v2` 的加密协议、JSON 字段和客户端解密逻辑，不要求客户端升级。

## 1707 / 1708 状态说明

- 之前将 1707/1708 作废，是在尚未定位 `/appstore` 故障起点时的风险控制措施。
- 现已确认故障起点是 2026091704 对 `renewal_entry` 的强制查询依赖，并已由 2026091710 修复。
- 1707/1708 的性能功能本身现已重新纳入 2026091711。
- 历史 `source-v2026091707` / `source-v2026091708` 标签仍不建议单独部署，因为它们不包含 1710 的 schema-adaptive 修复；后续统一以 2026091711 为基线。

## 保留的业务功能

- 保留续费入口：字段存在时 `renewal_entry=1` 仍保持 `lock=1`、`downloadURL=''`。
- 保留三种卡密用途、授权叠加、`apiface` 签名和换绑额度逻辑。
- 保留删除卡密/App 时同步清理 `fa_kami_app` 映射；仅到期、隐藏、停用、编辑不清映射。
- 保留清空换绑记录/授权事件后回到授权总览并禁止旧页面缓存。

## 验证要求

- PHP 7.0 全回归必须通过。
- MySQL 5.7 迁移链及“缺少 renewal_entry 的旧表兼容查询”必须通过。
- 1707/1708 性能专项测试必须通过：bkey cache、App cache fail-open、gzip 字节等价、性能契约和 50k benchmark。
- 在线更新 ZIP 必须包含 `SourceAppRecord.php`、`SourceAppRepository.php`、`SourceEncryptionProvider.php`、`SourcePerformance.php`、`SourceResponse.php` 与新版 `App.php`。
- 必须发布 `zonoe-online-update.zip` 与 SHA256，并通过 2026091710 → 2026091711 的真实 GitHub 在线升级 E2E。

## 在线更新

- 正式版本：`2026091711`
- 基线：`2026091710`
- GitHub Release：`source-v2026091711`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
