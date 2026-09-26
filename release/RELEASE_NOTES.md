# ZONOE 软件源 2026092409

## 更新内容

本版本以 `source-v2026092408` 为升级基线，专门重构 **Dylib 验证中心** 的后台交互。验证协议、HMAC、Bootstrap、权限模型、数据库结构和 Objective-C 生成协议保持不变；2409 只收敛 UI 信息架构，降低日常配置复杂度。

### Dylib 验证中心改为 5 个主入口

原 2408 的 7 个大块平铺结构收敛为：

1. 概览
2. 版本
3. 通知
4. 验证记录
5. 高级

日常操作默认集中在“概览”，低频技术配置放入“高级”。

### 概览

概览保留：

- Dylib Key / 名称
- 验证密钥生成与轮换入口
- 默认离线容错 / 默认失败动作
- Dylib 列表与启用、停用、编辑、删除
- OC 接入代码生成

OC Codegen 不再作为一个编号大区块动态插入页面，而是固定渲染到概览中的 `codegen-slot`。

### OC 接入代码进一步简化

默认只展示：

- 目标 Dylib
- 版本
- 预览
- 生成并下载 ZIP

类前缀、最低 iOS、请求超时收进“选项”折叠区；代码预览区域只有实际执行预览后才展开。

生成文件和协议保持 2408 不变：

- `ZONDylibConfig.h`
- `ZONDylibConfig.m`
- `ZONDylibVerify.h`
- `ZONDylibVerify.m`
- `GeneratedConfig.json`
- `INTEGRATION.md`
- `generation-manifest.json`

### 高级设置

以下内容从首页平铺移入“高级”：

- API Base URL
- Bootstrap URL
- Verify Path
- 游戏更新默认弹窗
- Protocol v1 / v2 HMAC 接入说明
- 权限模型说明

接入协议和权限模型默认折叠，只有需要调试或审计时才展开。2409 仍保留 v1 canonical 文档，确保已发布旧客户端的兼容契约在后台可审计；v2 App Identity/HMAC 说明同时保留。

### 通知与验证记录

远程通知单独进入“通知”页签；验证日志单独进入“验证记录”页签。Bootstrap Table 在隐藏页签切换后自动 `resetView`，避免首次打开隐藏表格时宽度异常。

### 兼容性

2409 没有修改：

- `DylibVerificationService`
- `DylibRuntimeAccessService`
- `DylibRuntimeConfigService`
- `/index/dylib_verify/config`
- `/index/dylib_verify/verify`
- v1 / v2 HMAC canonical 格式
- Runtime Config 签名格式
- OC 生成结果协议
- MySQL 表结构

因此 2408 已接入的 Dylib 客户端不需要重新适配协议。

### 发布前兼容收口

- 恢复“高级 → 接入说明”中的 v1 HMAC canonical 文档，但仍保持默认折叠，不恢复旧版平铺页面。
- 将 2406 历史测试中已经过时的“1～6 编号页面顺序”断言升级为 2409 的 5 Tab 顺序契约；App Identity、卡密 scope、Bootstrap、Last-Known-Good、HMAC、数据库和在线更新断言均保持不变。

### 已完成验证

- `OC Codegen CI #6` / Run `36227164466`：success（2409 功能分支）。
- `OC Codegen CI #7` / Run `36227565448`：success（v1 文档兼容修复复验）。
- `IPA Online Update Release Gate #138` / Run `36227697213`：success。
- PHP 7.0：生成器、Dylib UI、历史 Dylib signing/runtime contracts 通过。
- JavaScript syntax check：`dylib_center.js`、`dylib_codegen_inline.js` 通过。
- 原 OC Codegen contract：通过。
- 新 `dylib_center_ux_contract_test.php`：通过，强制验证 5 Tab、Codegen 位于概览、接入说明/权限模型默认折叠。
- 在线更新包内容 Gate：确认 Dylib Center view 与两份 JS 已进入更新包。
- MySQL 5.7 migration regression：success；2409 无新增数据库 migration。

目标升级路径：`source-v2026092408 -> source-v2026092409`。
