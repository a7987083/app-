# 一键部署

执行：

```bash
curl -fsSL https://raw.githubusercontent.com/a7987083/app-/main/deploy.sh | bash
```

项目要求 PHP >= 8.2，网站运行目录为 `/public`。脚本处理源码、PHP/扩展/函数检查、Composer、依赖、`.env`、APP_KEY、权限、缓存和 storage link。

本项目的 MySQL 参数保存在 `config/api.php`，`config/database.php` 读取 `config('api.mysql.*')`。项目已有 `/install` 页面及 `POST /api/install` 安装入口，因此一键脚本不绕过项目自身的 InstallController，不自行猜测数据库、管理员、授权初始化动作。基础部署完成后访问 `/install` 完成业务安装。
