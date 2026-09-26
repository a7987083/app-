# ZONOE 软件源 2026092411

## 更新内容

本版本以 `source-v2026092410` 为升级基线，重点整理 Dylib 验证中心的日常使用体验，并补齐远程通知与验证记录管理能力。

### 当前 Dylib 统一

- 页面增加统一“当前 Dylib”上下文。
- OC 接入代码、版本管理、接入说明和验证记录跟随同一 Dylib。
- 修复在“OC 接入代码”中已经选择 Dylib，但“接入说明”仍显示“请选择已注册 Dylib”的问题。
- 接入说明中的运行配置 URL 会直接显示实际 `dylib_key`。

### 版本管理易用性

- `Build` 在后台显示为“内部构建号”，并解释其用于同一版本多次重新编译的区分。
- SHA256 明确说明：留空表示不校验文件指纹；填写后客户端 Dylib 文件必须与登记值一致。
- 离线容错改为“离线可用”语义，说明服务端暂时不可访问时缓存验证结果的可用时长。
- 验证失败动作全面中文化：允许继续使用、禁用受保护功能、只显示提示、完全阻止使用。
- 客户端提示改称“用户提示”，说明用于版本/校验异常时给客户端显示。
- SHA256、离线容错、失败动作、用户提示归入默认折叠的“高级校验设置”。

### 远程通知

- 通知列表新增“启用 / 停用 / 删除”。
- 删除操作增加二次确认。
- 最低权限不再直接显示英文枚举：
  - `basic` -> 普通授权
  - `app_plus` -> 指定 App 高级授权
  - `global_plus` -> 全软件源高级授权
- 通知表单增加用途说明，明确通知 Key、Revision、优先级属于低频高级字段。

### 验证记录

- 默认每页 `1000` 条，可切换 `100 / 500 / 1000`。
- 新增组合筛选：Dylib、验证结果、BundleID、Dylib 版本、UDID Hash 前缀、开始/结束时间。
- 新增单条删除、勾选批量删除、删除当前筛选结果。
- “删除当前筛选结果”要求前端至少存在一个筛选条件，降低误清空风险。

### 兼容性

2411 不修改：

- `/index/dylib_verify/config`
- `/index/dylib_verify/verify`
- v1 / v2 HMAC canonical
- Runtime Config 签名协议
- Objective-C Generator 2.1.0 的协议输出
- 现有数据库表结构

因此从 2410 升级不需要调整现有客户端协议或新增数据库 migration。

### 已完成验证

- `OC Codegen CI #10` / Run `36232452762`：success。
- PHP 7.0 lint：success。
- JavaScript syntax check：success。
- OC Codegen contract：success。
- Dylib Center UX contract：success。
- 在线更新包内容检查：success。
- MySQL 5.7 migration regression：success。

目标升级路径：`source-v2026092410 -> source-v2026092411`。