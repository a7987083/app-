# Software Source Development Handoff

## 当前基线

- Repository: `a7987083/app-`
- Release branch: `release/2026091905-phase20-setting-hotfix`
- Work branch: `dev/2026091914-ipa-workset`
- Last published release: `2026091913`
- Current release target: `2026091914`
- Real server last verified version: `2026091911`

## 1914 当前实现

- MySQL 软件源 `bt1a` 为 IPA 扫描范围权威来源；无启用软件源时不再扫描整个 OpenList。
- OpenList 只负责文件访问、MD5、目录缓存、HTTP Range。
- 后台解析固定一次 1 个，原子领取，支持 `needs_reparse` 与失败退避。
- 独立 `fa_ipa_parse_cache` 按 MD5 + size + parser version 保存可复用解析结果。
- `fa_ipa_metadata` 是 active workset；`referenced=0` 且未绑定的记录可安全回收。
- metadata 筛选查询 count/rows 独立构造，避免 ThinkPHP/PDO 参数复用问题。
- 刷新失败会标记旧数据；OpenList 停用后扫描/强刷/解析/测试连接 fail closed。
- queued heartbeat=0 僵尸任务可恢复；后台 spawn 优先 CLI PHP 并加强启动判定。

## 验证状态

- PR #14 Phase 20 IPA Management Run #118：四个 job 全绿。
- Regression Checks / Phase14 Production Hardening / Phase 17.2 Authorization Integrity：全绿。
- Phase 20 Workset MySQL57 Run #1：真实 MySQL 5.7 最新迁移连续执行两遍成功。
- 正式 ZONOE Source Release 1914：尚未触发。
- 真实服务器 1914：尚未验证。

## 发布纪律

- `2026091911 / 1912 / 1913` 均冻结。
- 当前正式目标只能是 `2026091914`；发布后若再改内容必须升 1915。
- 已提交 / CI 通过 / Release 发布 / 生产验证必须分开记录。
- workflow 仍含旧 `--clobber` 路径，因此绝不能复用已存在 VERSION/Tag。

## 接手顺序

1. 核对 dev HEAD 和 release branch 是否只差 fast-forward。
2. fast-forward 正式 release 分支到最终 1914 HEAD。
3. 观察 ZONOE Source Release 全部 jobs。
4. Release 创建前失败：继续修 1914；Release 创建后失败：冻结 1914，修复进入 1915。
5. 全绿后记录 Release Tag / Asset SHA256 / E2E。
6. 最后执行真实服务器在线升级和 Phase 20 回归。
