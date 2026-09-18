# ZONOE 软件源 2026091804

## Phase 19.3.1 — 高并发 / 大数据量 / 加密路径完善 + API Center 真机修复

本版本以 `2026091803` 为稳定基线，继续完成 Phase 19.3 原定的高并发、大数据量和加密路径性能目标，并修复 1803 真机测试暴露的 API Center 三个交互问题。客户端协议、原 `/appstore` 地址和授权边界保持不变。

## API Center 真机修复

- “请求日志 → 刷新日志”改为 AJAX 局部刷新，不再使用 `location.reload()`，不会跳回“基础配置”。
- API Center 外层/内层 Tab 使用 sessionStorage 保持状态，必要的整页刷新后仍返回原 API 页签。
- API列表“开启/关闭”改为 FastAdmin `Backend.api.ajax` + ThinkPHP 生成的后台 URL；数据库写入后重新读取校验，只有真实持久化成功才返回成功。
- API列表“测试”改为真实动作，并自动跳转到“测试API”且选中对应接口。
- “测试API”新增接口下拉列表，自动带出完整 URL、Method、鉴权说明；提交按 `endpoint_key` 查找，不再依赖隐藏数据库 ID。
- 请求日志增加 50/100/200 条选择和局部刷新状态提示。
- FastAdmin 原生 `/api/*` 仍不纳入项目 API Center。

## Phase 19.3.1 高并发验证

新增真实 HTTP 负载门禁，不再只做 PHP 函数级测试。CI 拓扑：

- MySQL 5.7
- 8 个 PHP 7.0 HTTP worker
- Nginx upstream
- wrk 并发请求真实 `/appstore`
- 5,000 / 10,000 / 20,000 App
- 明文 / 普通加密
- Guest / 全源授权
- c8 / c16 / c32
- 记录 RPS、P50、P95、P99、错误率、响应大小、冷/热请求耗时

最终 Phase 19.3.1 feature gate Run `35291222266` 九个场景全部零 HTTP/Socket 错误。该结果用于版本回归和瓶颈比较，不代表生产服务器的容量承诺。

最终门禁中的代表数据：

- 20,000 App 明文 Guest c16：约 20.86 RPS，P95 约 1044.7 ms，响应约 6.69 MB，错误 0。
- 20,000 App 明文 Guest c32：约 22.89 RPS，P95 约 1592.8 ms，响应约 6.69 MB，错误 0；增加并发后吞吐基本不再线性增长，测试机进入大响应体/worker 饱和区。
- 20,000 App 加密 Guest c8：约 11.40 RPS，P95 约 743.1 ms，响应约 9.08 MB，错误 0。
- 20,000 App 加密全源授权 c8：约 10.88 RPS，P95 约 712.3 ms，错误 0。

## 加密热路径优化

- Legacy RC4 大响应新增 keystream 文件缓存；相同 bkey 下复用确定性 RC4 keystream。
- 实际大块 XOR 使用 PHP 二进制字符串 XOR，而不是每个响应对数百万字节执行 PHP 层逐字节循环。
- keystream 文件位于非 Web 根目录，使用锁、临时文件原子替换和 0600 权限；异常时 fail-open 回退到原 RC4 实现。
- 本地加密新增直接 JSON 入口，移除旧路径中“JSON → Base64 → 立刻 Base64 decode”的大块内存往返。
- 只有真正走外部 Nuosike 或本地失败回退时才创建 Base64 content。
- V2 随机 key 路径继续使用原参考实现，不使用 Legacy keystream 复用。

6.5 MB Legacy RC4 微基准在 Phase 19.3.1 CI 中验证：

- 原逐字节参考实现约 1.13 s。
- keystream cache hit 约 12 ms。
- 本轮观测约 91× 快速路径加速。
- 原实现、缓存快路径、兼容 `encryptEncodedContent`、新 `encryptJson` 的密文均做字节等价验证。

真实 HTTP 同口径矩阵中，加入直接 JSON 本地加密后，20,000 App 加密 Guest 在一轮优化验证中从约 10.45 RPS / P95 937.8 ms 观测到约 13.39 RPS / P95 658.8 ms；CI runner 存在硬件波动，因此该差异作为优化验证数据，不作为生产容量承诺。

## 大数据量

- 继续保留 20,000 App 映射/JSON 契约。
- 20,000 App JSON 约 6.9 MB。
- 新旧加密前 JSON 字节完全一致。
- MySQL 5.7 热路径索引、120 秒 App 行缓存、generation/revision 主动失效机制继续保留。

## 发布门禁

从本版开始，正式 Release 的 `package-and-release` 必须同时等待：

1. PHP 7.0 全量回归；
2. MySQL 5.7 迁移回归；
3. Phase 19.3.1 真实 HTTP 负载矩阵。

任意一项失败都不会创建正式在线更新 Release。

同时保留 Legacy RC4 固定向量、bkey 缓存、加密策略和 V2 模式兼容测试。

## 兼容性

- 客户端无需更新。
- `/appstore` 地址不变。
- Guest / 全源卡 / 指定 App 卡权限隔离不变。
- 普通加密输出协议保持兼容。
- V2 加密协议保持兼容。
- 远程 `dylib()` 内部授权逻辑本版仍不重构。
- PHP 7.0、MySQL 5.7 继续作为发布兼容基线。

## 在线更新

- 正式版本：`2026091804`
- 基线：`2026091803`
- GitHub Release：`source-v2026091804`
- 发布资产：`zonoe-online-update.zip` + `zonoe-online-update.zip.sha256`
