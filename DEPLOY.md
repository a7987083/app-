# 一键部署

```bash
curl -fsSL https://raw.githubusercontent.com/a7987083/app-/main/deploy.sh | bash
```

脚本处理 PHP >= 8.2 检查、仓库源码、Composer、依赖、扩展/禁用函数检查、`.env`、APP_KEY、权限、缓存和 storage link。网站运行目录必须是 `<项目目录>/public`。

## 项目特有安装链

源码确认：`config/database.php` 的默认 mysql 连接从 `config('api.mysql.*')` 读取，而实际配置位于 `config/api.php`；路由提供 `GET /install` 和 `POST /api/install`，后者进入 `InstallController::install`。控制器还包含数据库连接检查、配置写入、migration、storage:link、管理员创建以及项目接口/授权相关处理。

因此部署脚本有意不重新实现或绕过这段业务安装逻辑。基础环境准备完成后访问 `/install`，由项目自己的安装器完成数据库和业务初始化，避免漏掉项目特有步骤。
