# ZONOE 软件源开发路线图

## 当前稳定基线

- Repository: `a7987083/app-`
- Stable release branch: `release/2026091807-unified-announcement-expiry`
- Stable phase/version: `Phase 19.4.2 / 2026091807`
- Release commit: `1ef93306dec0a7b09bb99df55b785511d5b8b4c6`
- Release: `source-v2026091807`
- Unified-announcement CI Run `35306216020`: SUCCESS
- Formal Release Run `35306308086`: SUCCESS
- Online-update E2E `2026091806 -> 2026091807`: SUCCESS

## Phase 19.4.2 — 已发布

- [x] 公告只暴露一套授权时间。
- [x] 后台移除全源/部分/验证独立时间按钮。
- [x] `[授权状态]` / `[到期时间]` / `[剩余时间]` 共用同一有效授权选择逻辑。
- [x] 优先级：全软件源 → 指定 App → 仅验证。
- [x] 无有效授权统一显示“已过期或未解锁本源”。
- [x] `[授权摘要]` 收敛为单一时间模型。
- [x] 历史公告变量自动迁移。
- [x] 旧变量运行时兼容并映射到同一时钟。
- [x] 1806 → 1807 在线更新 E2E 通过。
