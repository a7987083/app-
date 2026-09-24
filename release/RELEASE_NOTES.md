# ZONOE 软件源 2026092405

## 更新内容

本版本以 `source-v2026092404` 为唯一升级基线，完善 Dylib 管理生命周期和接入可视化；不改变现有 Dylib 验证协议、版本状态枚举或 BundleID 绑定规则。

### Dylib 生命周期管理

- 已注册 Dylib 增加编辑、停用、启用、删除和接入说明。
- 编辑时 `dylib_key` 只读，因为它参与客户端查找和 HMAC 签名契约；验证密钥留空表示不轮换。
- 停用只写入 `enabled=0`，版本、BundleID 授权、验证历史与其他配置全部保留。
- 验证服务仍按原逻辑只查询 `enabled=1` 的 Dylib；停用后现有客户端得到既有 `dylib_unknown` / `block` 结果。
- 删除采用历史保护：仅从未产生版本、BundleID 授权和验证记录的注册项允许硬删；已有业务历史的 Dylib 禁止删除，应使用停用。

### 页面与接入说明

- 页面结构调整为：注册 → 接入 → 游戏授权 → 版本控制 → 验证记录。
- 接入说明直接来自现有服务端与 Objective-C 客户端：`POST /index/dylib_verify/verify`。
- 请求字段、HMAC-SHA256 canonical 字段顺序和响应字段均保持现有协议，不新增或猜测 endpoint。
- 现有 `clients/ios/ZONDylibVerify/ZONVerifyClient.h/.m` 继续兼容；不会替换旧 `Index::dylib()` / `Index::apiface()`。

### 中文化与日志可读性

- `active/testing/deprecated/blocked/revoked`、`allow/disable_feature/show_message/block` 和服务端 `result_code` 均保持底层原值，仅在后台 UI 映射中文。
- 验证记录改为“验证结果 / 客户端动作 / 设备标识哈希 / 游戏 BundleID”等管理员可读字段，UDID 仍只显示哈希前 12 位。

### 安全与兼容性

- 删除操作使用项目现有 `Layer.confirm` 二次确认；写操作继续使用 `Fast.api.ajax` 和 Backend 权限/CSRF 链。
- 不新增业务表或 SQL 迁移。
- PHP 7.0 / MySQL 5.7 兼容要求不变。
- 旧客户端协议、已有版本规则、BundleID 绑定和签名算法均不变。
- 在线更新继续沿用现有 `UpdateManager / UpdateInstaller` 下载、SHA256、备份、覆盖、版本写入和失败回滚流程。

目标升级路径：`source-v2026092404 -> source-v2026092405`。
