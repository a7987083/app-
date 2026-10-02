# ZONOE 软件源 2026092437

## 更新内容

### 软件说明换行修复

- 修复“软件说明”中的换行在客户端显示为 `@@@` 的问题。
- `versionDescription` 现在从源头恢复为真实换行，再由 `json_encode` 生成标准 JSON 转义。
- 明文与加密软件源统一使用相同的换行语义。
- 移除 Response 层对 `@@@` 的二次替换依赖，避免加密前已经固化占位符。
- 升级 Legacy App 映射/Body/Encrypted JSON 缓存 Key，避免上线后短时间继续命中旧 `@@@` 缓存。

### 验证

- VersionDescription Newline 2437 CI #1：SUCCESS。
- PHP 7.0 syntax：SUCCESS。
- 现有 AppStore 回归：SUCCESS。
- SourceResponse 回归：SUCCESS。
- Semantic equivalence：SUCCESS。
- Encrypted newline regression：SUCCESS。

## 兼容性

- 基于正式 `source-v2026092436`。
- 不改变软件源字段结构。
- 不改变授权、卡密、下载权限和加密协议。
- 仅修复 `versionDescription` 的换行编码链。

## 升级路径

`source-v2026092436 -> source-v2026092437`
