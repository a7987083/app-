# 一键部署

项目要求 PHP >= 8.2。仓库 `auto_install.json` 指定运行目录 `/public`，并要求 fileinfo、redis 等扩展以及 proc_open、pcntl_signal、pcntl_alarm、symlink 等函数。

## 执行

```bash
curl -fsSL https://raw.githubusercontent.com/a7987083/app-/main/deploy.sh | bash
```

脚本负责：源码、PHP/扩展/函数检查、Composer、依赖、`.env`、APP_KEY、目录权限、缓存和 storage link。

## 数据库安装

此项目不是标准 Laravel `DB_*` 数据库读取链：`config/database.php` 的 mysql 连接读取 `config('api.mysql.*')`，实际参数保存在 `config/api.php`。

因此脚本保留项目自身安装流程，不绕过混淆的业务安装控制器：

1. 脚本完成基础部署；
2. 网站运行目录指向 `<项目目录>/public`；
3. 访问 `/install`；
4. 页面通过 `POST /api/install` 调用 `InstallController::install` 完成数据库、管理员和项目自身安装动作。

这样可以避免脚本自行猜测业务安装逻辑，导致遗漏授权、管理员初始化或其他项目特有步骤。
