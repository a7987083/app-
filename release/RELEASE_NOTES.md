# ZONOE 软件源 2026092407

## 更新内容

本版本以 `source-v2026092406` 为升级基线，在现有软件源后台中新增 Objective-C 工程在线生成能力，并完整接入既有软件源在线更新链路。

### Objective-C 在线工程生成

- 后台新增“OC 工程生成”入口。
- 直接读取当前 `ApiEndpointRegistry` 作为接口元数据来源，不维护第二套接口清单。
- 生成前支持 Draft 编辑：工程名、类前缀、Base URL、最低 iOS、超时、User-Agent，以及接口启用状态、Path、Method、Auth、说明。
- Draft 修改仅影响本次生成，不会回写线上 API 配置。
- 预览阶段输出最终配置 JSON、OC/文档文件列表和每个文件内容。
- 最终生成前必须携带预览返回的 `config_hash`；配置发生变化时拒绝生成并要求重新预览。

### 生成文件

默认输出：

- `ZONAPIConfig.h/.m`
- `ZONAPIEndpoints.h/.m`
- `ZONAPIClient.h/.m`
- `GeneratedConfig.json`
- `API_REFERENCE.md`
- `INTEGRATION.md`
- `generation-manifest.json`

生成器会记录 config SHA256 与文件 SHA256，保证同一配置可追溯。

### 接口与安全约束

- UDID、卡密、Token、HMAC 等敏感值不会写死进生成源码；由请求参数或 `headerProvider` 在运行时注入。
- v1/v2 HMAC、字段顺序等既有协议不由生成器擅自改写。
- 当前 `ApiEndpointRegistry` 尚未结构化完整 Response Schema，因此本版不会猜测或伪造 Objective-C Model；后续补齐响应元数据后再生成 Models。
- 最终 ZIP 存入 `runtime/codegen` 临时目录，使用随机下载 token、SHA256 与过期时间控制，不写入持久业务配置。

### 软件源在线更新

- 新增文件已加入 `release/online-update-files.txt`。
- `release/sql/2026092407_oc_codegen.sql` 由现有更新包构建器自动收入 `mysql/`。
- 继续沿用现有 `UpdateManager / UpdateInstaller`：GitHub Release 检测、SHA256、备份、SQL 执行、文件覆盖、版本写入和失败回滚逻辑均不另起一套。
- 因此已有 `2026092406` 软件源可在后台现有“检查更新 / GitHub 在线更新”流程中升级到 `2026092407`。

### 兼容与验证

- PHP 7.0 语法检查通过。
- JavaScript syntax check 通过。
- Objective-C generator contract test 通过，验证 10 个生成文件与稳定 config hash。
- 在线更新 ZIP 内容 Gate 通过，确认包含生成器核心、Controller、View、JS 与 2407 SQL。
- MySQL 5.7 migration test 通过；同一迁移连续执行两次保持 1 个父菜单 + 5 个权限节点，不重复。
- `OC Codegen CI #3` / Run `36132572050` 已成功完成。

目标升级路径：`source-v2026092406 -> source-v2026092407`。
