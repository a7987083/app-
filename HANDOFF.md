# Software Source Development Handoff

## 当前基线

- Repository: `a7987083/app-`
- Release branch: `release/2026091905-phase20-setting-hotfix`
- Work branch: `work/2026091913-release-fix`
- Last published release: `2026091912`
- Current release target: `2026091913`
- Real server last verified version: `2026091911`

## 当前实现

- OpenList 配置已使用 MySQL durable persistence。
- IPA MySQL source/metadata、引用目录发现、目录缓存、扫描分页均已进入发布线。
- Phase 20 online-update payload 含 program 文件与有序 MySQL migration。
- FastAdmin PATH_INFO/action、Range Parser、绑定、治理、恢复、Retention、appstore 协议语义保持。

## 1912 状态

- GitHub Release `source-v2026091912` 已创建并冻结。
- Source Release #159：PHP 7、MySQL 5.7、HTTP load、Phase 20 integration、package-and-release 均成功。
- 最终 GitHub online-update E2E 因 Release Notes 标题契约失败；不是业务代码、MySQL 或更新包构建失败。

## 1913 修改

- 版本元数据升级到 1913。
- Release Notes 改用 `更新内容` 标题。
- Phase 20 UI contract 不再绑定具体版本号，只验证合法版本格式。
- 长期状态文件同步维护。

## 发布规则

- 1911、1912 均视为冻结历史版本，不再覆盖。
- 后续严格：1913 -> 1914 -> 1915 -> ...。
- 已提交 / CI 通过 / Release 发布 / 真实服务器验证必须分开记录。
- 当前 workflow 仍含旧的 `--clobber` 路径，因此操作纪律上绝不能重复使用已发布 VERSION；后续应单独做流程硬化。

## 接手注意

1. 先核对 release 分支 HEAD 是否已快进到工作分支最终 HEAD。
2. 查看最新 ZONOE Source Release Run。
3. 若失败，只修第一处真实错误，不绕过 Gate。
4. CI 全绿后记录 Release Tag、Asset SHA256、E2E 结果。
5. 最后再做真实服务器升级和 Phase 20 功能回归。
