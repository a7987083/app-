# ZONOE 软件源 2026092418

## 更新内容

### IPA 总览：移除重复入口

本版本以 `source-v2026092417` 为升级基线，撤销 2416/2417 中新增但与现有 IPA 总览重复的独立 `软件源 -> 解析 IPA` OpenList 页面，继续以现有 IPA 总览/解析中心作为唯一入口。

- 删除独立 `IpaOpenlist` controller/view/js 源码。
- 删除旧的 `2026092416_ipa_openlist_menu.sql` 新增菜单迁移。
- 新增幂等清理迁移，在线升级后自动删除 `ipa_openlist/index`、`ipa_openlist/save`、`ipa_openlist/test` 权限/菜单规则。
- 保留现有 IPA 总览里的 OpenList 数据源、扫描、解析、比对、写回等功能。

### IPA 资产搜索修复

修复“总览 -> IPA 资产”搜索看似存在、实际过滤能力不完整的问题。

- 前端表格明确将 `search` 参数发送到 `ipa_center/assets` 服务端接口，并保留服务端分页。
- 后端搜索范围扩展到：IPA 文件名、远端路径、Bundle ID、App 名、Version、Build、状态、OpenList Source 名。
- 数字关键词同时支持按 IPA Asset ID / Source ID 精确匹配。
- 中文状态词支持：待解析、解析中、已解析、解析失败、已缺失。
- 搜索结果的 `total` 与 `rows` 均基于过滤后的数据库查询，不再是当前页前端假过滤。
- 增加发布回归契约，确保后端搜索字段与前端 queryParams 不会再次被移除。

### 兼容性

2418 不改变：

- OpenList 数据源表结构；
- IPA 扫描/解析主链；
- Dylib Protocol v1/v2；
- 卡密/授权现有业务协议；
- GitHub 在线更新 SHA256、备份、数据库迁移与失败回滚安全链。

目标升级路径：`source-v2026092417 -> source-v2026092418`。
