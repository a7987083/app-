# ZONOE 软件源 2026092415

## 更新内容

### 更新备份删除策略

本版本以 `source-v2026092414` 为升级基线，调整更新运维中心的备份删除与保留策略。

- 取消“被更新历史引用的备份禁止删除”的回滚保护限制。
- 所有更新备份均允许单个删除、批量删除或删除全部备份。
- 保留备份 ID、`realpath` 与备份根目录边界校验，仍禁止越界/任意路径删除。
- 保留 `protected` / `protected_reason` 响应字段以兼容现有前端与旧调用方，但 2415 中保护状态固定关闭。
- 删除仍被历史记录引用的备份后，对应历史回滚将不可用；界面已明确提示该行为。
- 安全清理仍保留运行中任务状态，过期历史与备份按保留期清理，不再因历史引用跳过备份。

### 前端与回归

- 运维中心备份列表不再禁用历史引用备份的选择与删除按钮。
- “删除全部未保护备份”调整为“删除全部备份”。
- 更新 Phase14.3 与 2414 备份/日志契约测试，覆盖 rollback protection 已移除的新语义。

### 自动在线发布

- 继续使用中央 `Auto Online Release Gate`。
- Gate 等待同一 commit 的其他 CI 全部结束后校验 `VERSION`、`public/update/ver.txt`、`ver.json`。
- 全部通过后自动触发 `ZONOE Source Release`，生成 `zonoe-online-update.zip`、SHA256 与 GitHub Release。

### 兼容性

2415 不改变：

- Dylib Protocol v1 / v2 canonical；
- Dylib 在线验证 wire contract；
- Runtime Config 签名；
- 卡密/授权现有业务协议；
- 现有数据库结构。

目标升级路径：`source-v2026092414 -> source-v2026092415`。
