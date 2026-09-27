# ZONOE 软件源 2026092416

## 更新内容

### 解析 IPA：OpenList Token 连接

本版本以 `source-v2026092415` 为升级基线，开始按阶段重构 IPA 解析功能。本阶段只交付 OpenList 连接，不启用新的扫描/解析主链。

- 在原版 FastAdmin / Bootstrap 后台 UI 中新增 `软件源 -> 解析 IPA` 入口。
- 第一阶段页面提供 OpenList 地址、Token、IPA 根目录、请求超时、测试连接与保存配置。
- OpenList API 认证继续使用原始 `Authorization: <token>` 请求头，不使用 Bearer 前缀。
- Token 继续使用现有 `SecretBox` 加密保存；编辑时留空不覆盖旧 Token，页面不回显明文。
- “测试连接”实际请求 OpenList `/api/fs/list`，同时验证 API 可达、Token 权限与根目录访问。
- 新增幂等菜单迁移 `release/sql/2026092416_ipa_openlist_menu.sql`，不写死安装实例菜单 ID。
- 本阶段不改变 IPA 扫描 Worker、解析 Worker、数据库比对与写回逻辑。

### 自动在线发布

- 继续使用中央 `Auto Online Release Gate`。
- Gate 等待同一 commit 的其他 CI 全部结束后校验 `VERSION`、`public/update/ver.txt`、`ver.json`。
- 全部通过后自动触发 `ZONOE Source Release`，生成 `zonoe-online-update.zip`、SHA256 与 GitHub Release。

### 兼容性

2416 不改变：

- Dylib Protocol v1 / v2 canonical；
- Dylib 在线验证 wire contract；
- Runtime Config 签名；
- 卡密/授权现有业务协议；
- 现有 IPA 扫描/解析主链行为。

目标升级路径：`source-v2026092415 -> source-v2026092416`。
