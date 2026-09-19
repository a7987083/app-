# ZONOE 软件源 2026091904

## 更新内容

本版本基于 `source-v2026091903`，收敛 Phase 20 IPA 管理中心长期存储模型，并修复 OpenList 设置保存/错误提示链路。

### OpenList 设置与保存

- “保存 / 测试连接”继续使用 FastAdmin `IpaCenter` Controller 生命周期绑定。
- `source_save` / `source_test` 不再要求升级后额外给管理员组分配两个隐藏权限节点；两者继承 `ipa_center/setting` 权限。
- FastAdmin Ajax 在 HTTP 4xx/5xx 时优先显示后端 JSON `msg` 或响应正文，不再只显示没有诊断价值的 `error`。
- OpenList API 继续固定为 `/api/fs/*`；Authorization 继续直接发送 OpenList 令牌，不增加 `Bearer`。

### OpenList 配置脱离 MySQL

- OpenList URL、目录、公开下载地址、调度参数、健康状态和加密 Token 改为保存在 `runtime/ipa/openlist.json`。
- Token 使用安装本地独立密钥保护；可使用 `IPA_CONFIG_KEY`，未提供时自动创建 `runtime/ipa/.openlist-key`。
- 新代码不再把 OpenList 配置写入 `fa_ipa_source`。
- 为在线升级安全保留旧表读取兼容：新配置不存在时会尝试一次性导入旧 `fa_ipa_source`，避免直接丢失历史配置。
- 本版本不破坏性 DROP `fa_ipa_source`，待所有升级实例完成迁移后再考虑物理删除。

### IPA metadata 数据库瘦身

- `fa_ipa_metadata` 继续作为长期可查询索引，保留路径、MD5、大小、Bundle ID、应用名、版本、解析状态等轻量字段。
- 新解析结果不再持续将 `raw_metadata_json`、`normalized_metadata_json`、`confidence_json` 大块 payload 写入 MySQL。
- 解析详情改为原子写入 `runtime/ipa/metadata-detail/<metadata_id>.json`。
- 后台列表读取外置 payload，并兼容尚未迁出的旧 DB JSON。
- 每次成功扫描最多迁移 50 条旧 payload，渐进清空旧 DB blob，避免一次升级造成大事务和 I/O 峰值。

### 扫描任务长期运行控制

- `fa_ipa_scan_task` 继续保留任务级历史摘要，便于长期排错和统计。
- `fa_ipa_scan_task_item` 暂时保留，因为 Parser、Range metrics 和治理缺失检测仍依赖它；但它被定义为短生命周期队列。
- `success` task item 保留 90 天后自动清理，与 Range metrics 最大统计窗口一致，避免多年运行无限增长。
- failed / retrying / interrupted 数据继续保留，避免破坏恢复语义。

### 长期保留的数据

继续使用 MySQL 保存真正适合关系数据库的数据：

- `fa_ipa_metadata`：轻量 IPA 索引；
- `fa_ipa_binding`：IPA 与 `fa_category` 的稳定业务绑定；
- `fa_ipa_scan_task`：任务级历史；
- `fa_ipa_governance_issue`：治理生命周期/人工状态；
- `fa_ipa_operation_log`：高风险操作审计与幂等恢复。

本版本优先停止数据库继续膨胀，不通过直接删表换取表面上的“表少”。

### 发布与兼容性

- 不修改原 `ZONOE Source Release` workflow 语义。
- 在线更新包继续由 `release/online-update-files.txt` + `tools/build_online_update.php` 生成。
- 新增 `IpaMetadataPayloadStore.php` 与 `public/assets/js/fast.js` 已加入在线更新文件清单。
- `UpdateIntegrity::files()` 监控文件未变化，`file_sign` 继续为 `f3f6e072f814d06403ce5e393967c9e2`。

### 已验证候选

- Phase 20 IPA Management Run #104：通过。
- Regression Checks Run #233：通过。
- Phase14 Production Hardening Run #87：通过。
- Phase 17.2 Authorization Integrity Run #18：通过。
- 版本元数据完成后，以最终 HEAD 再执行正式候选 CI，并在正式 Release 中验证 `source-v2026091903 -> source-v2026091904` 在线升级。
