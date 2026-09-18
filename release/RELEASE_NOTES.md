# ZONOE 软件源 2026091809

## 更新内容

本版本是项目管理性能 Hotfix，不引入 Phase 20 功能。

### 项目管理性能

- 新增/编辑页面不再为隐藏的父分类字段构建完整 `parentList` / Tree。
- 新增项目服务端固定 `pid=0`；编辑项目保留原 `pid`。
- 移除编辑路径中的 `Category::select()` 全表子节点校验，避免 2 万级数据下额外全表读取。
- 隐藏父级字段由 select/selectpicker 改成普通 hidden input。
- 项目列表继续保持默认 1000 条/页，分页选项保持 200 / 500 / 1000。

### CRUD 不自动刷新

- 新增成功后只关闭弹窗，不刷新父级 1000 行列表。
- 编辑成功后只关闭弹窗，不刷新父级 1000 行列表。
- 单行删除成功后使用 `bootstrapTable('remove')` 本地移除对应行，并阻止 FastAdmin 默认整表 refresh。
- 工具栏批量删除同样注册 no-refresh 回调，删除完成后本地移除已删除行。

### 性能回归门禁

新增 `tests/phase19_4_y_category_performance_test.php`，持续检查：

- Category 控制器不得重新出现 `buildParentList`。
- add/edit 路径不得重新出现 `Category::select()` 全表加载。
- 不得重新依赖 `fast\Tree` 构建隐藏父级树。
- add 固定 `pid=0`，edit 保留原 `pid`。
- 模板不得重新渲染 pid select/selectpicker 或 `parentList`。
- 默认分页必须保持 1000 条。
- 新增/编辑/删除不得恢复自动整表刷新。
- 在线更新清单必须包含本次 4 个业务文件。

### 在线更新

在线更新包包含：

- `application/admin/controller/Category.php`
- `application/admin/view/category/add.html`
- `application/admin/view/category/edit.html`
- `public/assets/js/backend/category.js`

`file_sign` 继续为 `f3f6e072f814d06403ce5e393967c9e2`，因为本版本没有修改 `UpdateIntegrity::files()` 监控的 4 个关键文件。

### 验证

- Phase 19.4.y 专项 PHP 7.0 / JS / 性能契约 / 在线更新包门禁。
- 正式 Release 继续执行 PHP 7.0 全回归、MySQL 5.7、HTTP load、更新包校验。
- 正式执行 `2026091808 → 2026091809` GitHub Release 在线更新 E2E。
