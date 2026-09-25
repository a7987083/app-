# ZONOE 软件源 2026092408

## 更新内容

本版本以 `source-v2026092407` 为升级基线，将 Objective-C 生成能力从独立后台页面迁入 **Dylib 验证中心**，并从“通用 API Client 生成器”收敛为“当前 Dylib 的验证接入代码生成器”。

### Dylib 验证中心内嵌生成

后台页面顺序调整为：

1. Dylib 注册
2. 接入说明
3. 权限模型
4. 运行配置与通知
5. OC 接入代码生成
6. 版本控制
7. 验证记录

2407 的独立“OC 工程生成”菜单在升级后隐藏，不再作为单独业务入口；已有权限节点保留用于升级兼容和历史角色数据。

### 生成结果收敛为 2 个 .h + 2 个 .m

真正加入现有 Objective-C / Dylib 工程并参与编译的文件固定为：

- `ZONDylibConfig.h`
- `ZONDylibConfig.m`
- `ZONDylibVerify.h`
- `ZONDylibVerify.m`

ZIP 同时附带：

- `GeneratedConfig.json`
- `INTEGRATION.md`
- `generation-manifest.json`

后三个文件只用于配置快照、接入说明和 SHA256 审计，不要求加入 Xcode Target。

### 与当前 Dylib 验证配置绑定

生成时先选择已注册 Dylib，再选择该 Dylib 的版本。生成器读取真实后台数据，包括：

- Dylib Key / 名称 / 启停状态
- 客户端 HMAC 验证密钥
- 默认离线容错与失败动作
- Runtime Config 版本
- API Endpoint 列表
- Bootstrap URL 列表
- Verify Path
- Dylib Version / Build / State / SHA256
- 版本级 offline grace / fail action / notice

版本选择顺序为：显式选择 → active → testing → 最新记录。

### 复用已验证的 Dylib 客户端协议实现

2408 不重新实现一套验证协议，而是以仓库现有 `clients/ios/ZONDylibVerify/ZONVerifyClient.h/.m` 为唯一模板来源，生成时转换为当前类前缀对应的 `*DylibVerify.h/.m`。

因此继续保留现有能力：

- Protocol v2 App Identity
- BundleID + Executable + Mach-O UUID + App Version/Build
- HMAC-SHA256
- 多 Bootstrap 配置发现
- 已验签 Last-Known-Good
- 多 API Endpoint 故障转移
- 旧 endpointURL 最终 fallback
- offline cache / offline grace
- `access_level`
- `permissions`
- `app_identity`
- `app_update`
- `notice`

### 配置与安全

`*DylibConfig.m` 会写入当前 Dylib 客户端协议实际需要的共享验证密钥，因此该文件应仅进入受控客户端工程，不应提交到公开仓库。

后台数据库凭据、后台管理 Token 等管理平面秘密不会写入生成结果。`GeneratedConfig.json` 中验证密钥只保留掩码形式；最终生成仍通过 `config_hash` 锁定预览状态，Dylib、版本、Runtime Config 或密钥变化时必须重新预览。

### 权限模型

新内部 `DylibCodegen` 服务的读取、预览、生成和下载动作都要求：

- 后台用户已登录；
- 显式拥有 `dylib_center/index` 权限。

2407 的 `general/occodegen/*` 请求路径继续作为兼容代理，但实际权限边界统一归属 Dylib 验证中心，避免旧浏览器缓存或升级瞬间出现接口断裂。

### 软件源在线更新

新增/调整文件已加入既有 `release/online-update-files.txt`，2408 migration 由现有更新包构建器收入 `mysql/`。升级继续沿用现有 `UpdateManager / UpdateInstaller`：GitHub Release 检测、SHA256、备份、SQL、文件覆盖、完整性校验、版本写入和失败回滚逻辑不变。

### 已完成验证

- `OC Codegen CI #5` / Run `36153250881`：成功。
- PHP 7.0.33：生成器、DylibCodegen、兼容 Controller 语法检查通过。
- JavaScript syntax check：Dylib Center 与内嵌 Codegen 通过。
- Generator contract：`files=7`，即 4 个 OC 源文件 + 3 个文档文件。
- Contract 明确验证 v2 HMAC、App Identity、Runtime Config、permissions、app_update，并验证权限边界为 `dylib_center/index`。
- MySQL 5.7.44：2407 → 2408 migration 可重复执行；独立菜单隐藏，历史权限节点不重复、不丢失。
- 在线更新包内容 Gate 已确认包含生成器、DylibCodegen、兼容 Controller、内嵌 JS、ZONVerifyClient.h/.m 与 2408 SQL。

目标升级路径：`source-v2026092407 -> source-v2026092408`。
