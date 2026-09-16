# ZONOE 软件源 2026091604

## 更新内容

- 修复 2026091603 后台“更新运维中心”点击后出现 404 的问题。
- 将运维中心入口从 `layer.open(type: 2, content: 'general/updatemaintenance/panel')` 改为 FastAdmin 原生 `Fast.api.open(...)`，由框架统一解析后台入口与路由地址，兼容自定义后台入口路径。
- `general/updatemaintenance/panel` 页面、运维诊断、安全清理、Phase 14.2 更新器故障防护保持不变。
- 软件源 `appstore` / `appstore_v2`、Nuosike 兼容加密、UDID、卡密、黑名单、授权时长与换绑次数行为保持不变。

## 兼容性

- 目标环境继续兼容宝塔 PHP 7.0。
- 不修改现有数据库结构和已有卡密数据。
- GitHub 在线更新继续使用 HTTPS、SHA256、备份、文件校验和回滚机制。
