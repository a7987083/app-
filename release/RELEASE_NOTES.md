# ZONOE 软件源 2026091703

## 更新内容

- 卡密统一恢复为“一次性首次激活凭证”：全软件源卡、仅验证卡、指定 App 卡一旦已激活，再次提交同一卡密均返回“解锁码已使用”。
- 后续持续授权验证统一走 `/index/index/apiface?udid=...`，不再通过重复提交仅验证卡获得新签名。
- `apiface` 保留原有 `code`、`msg`、`expire`、`ts`、`nonce`、`sign` 字段和旧 HMAC 原文 `udid|expire|ts|nonce`，确保旧客户端兼容。
- 在 `sign` 之后新增 `authorizations` 字段，只列该 UDID 当前已激活且未过期的授权类型；不存在的类型不返回。
- `authorizations` 按 `card_scope` 区分：1=全软件源、2=仅验证、3=指定 App，并分别返回各类型当前最晚 `expire`，避免三种授权只看到一个混合到期时间。
- 授权总览中的“换绑记录 / 授权事件 / 系统诊断”改为当前页面下方预览，不再点击后打开独立页面。
- 数据完整性审计扩展卡密状态、card_scope、kmyp、激活时间链、指定 App 映射、授权/换绑日志孤儿记录、黑名单一致性等检查。
- 修复数据完整性页面“重新审计”按钮路由错误导致 404，现固定回到 `integrity/index`。
- 在线更新清单已包含本版本涉及的前台、授权总览和数据完整性文件，可通过后台 GitHub 在线更新直接升级。
- 新增 Phase 17.2 PHP 7.0 专项 CI，并同步 Phase15 数据完整性契约；Regression、Phase14、Phase15、Nuosike 兼容和 Phase17.2 检查均已通过。

## apiface 成功响应示例

```json
{
  "code": 1,
  "msg": "ok",
  "expire": 1792961026,
  "ts": 1789592636,
  "nonce": "ee68fc07131c5692",
  "sign": "64位HMAC-SHA256十六进制签名",
  "authorizations": [
    {
      "scope": 1,
      "type": "全软件源",
      "expire": 1791000000
    },
    {
      "scope": 2,
      "type": "仅验证",
      "expire": 1792961026
    }
  ]
}
```

`authorizations` 只返回当前实际存在且未过期的授权类型。例如设备只有仅验证卡时，仅返回 `scope=2`。

## 卡密激活规则

- 卡密只负责首次激活，一次性使用。
- 首次激活继续保持现有激活响应和签名字段。
- 任意类型卡密已激活后再次提交，均返回“解锁码已使用”。
- 重复提交不会增加 `endtime`、不会修改 `usetime`、不会重复激活、不会重复写授权事件日志。

## 数据库

- 本版本不新增数据库结构。
- 继续使用现有 `card_scope`、`fa_kami_app`、`fa_authorization_event`、`fa_card_transfer_log` 和 `unlock_sign_key`。
- 既有 SQL 迁移保持幂等，可安全重复执行。

## 在线更新

- 正式版本：`2026091703`
- GitHub Release：`source-v2026091703`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
- 后台 GitHub 在线更新会从 `2026091702` 检测到并升级到 `2026091703`。
- 保留 HTTPS、SHA256、数据库/程序备份、文件覆盖校验、失败自动回滚和真实 Release E2E。

## 兼容性

- 目标环境继续兼容宝塔 PHP 7.0 与 MySQL 5.7。
- 不改变卡密字符串格式及日/周/月/季/年时长定义。
- 不改变旧 `sign` 的字段顺序和 HMAC 签名原文；新增授权明细放在 `sign` 后面。
