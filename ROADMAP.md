# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release: `source-v2026092435`
- Stable target: `bda2656a699e93162ea38514f21e5027fc92ece9`
- Current development: `2026092436`
- Development branch: `work/2026092436-retention-step1`

## 2026092436 — Long-Term Retention Hardening

### 已完成

- [x] 修复 `ipa:maintenance` 仍访问已删除 `fa_dylib_nonce` 的 P0 问题。
- [x] `fa_dylib_auth_challenge` 保留 1 天并自动清理。
- [x] Session / Verify Log / API Request Log 改为有界批量清理。
- [x] Device Key 每日主动清理 365 天未使用记录。
- [x] Scan Item 终态保留 7 天，Scan Job 终态保留 30 天。
- [x] Parse Attempt 保留 30 天。
- [x] Authorization / Card Transfer / Admin / Source Change 审计日志默认保留 365 天。
- [x] Update status/history/backup 自动复用 14/90/30 天 retention。
- [x] runtime/log 自动保留 30 天。
- [x] missing IPA 超过 365 天且无人工分类绑定时才允许清理主资产及派生数据。
- [x] 所有大表删除使用有界批次，避免单次超大 DELETE。
- [x] 增加 MySQL 5.7 retention 索引迁移。
- [x] maintenance systemd service/timer 纳入在线更新包。
- [x] 增加 PHP 7.0 / MySQL 5.7 / package 专项 CI。

### 待完成

- [ ] 完整 Release Gate。
- [ ] 正式发布 `source-v2026092436`。
- [ ] 生产服务器确认 systemd timer 已安装并 enabled。
