#!/usr/bin/env bash
set -Eeuo pipefail

DEPLOY_VERSION="1.1.0-source-flow"
REPO_URL="https://github.com/a7987083/app-.git"
BRANCH="main"
DEFAULT_DOMAIN="app3.zonoeios.xyz"
DEFAULT_DIR="/www/wwwroot/${DEFAULT_DOMAIN}"
PHP_BIN=""
APP_DIR=""
DOMAIN=""
COMPOSER=""

info(){ printf '[INFO] %s\n' "$*"; }
warn(){ printf '[WARN] %s\n' "$*"; }
die(){ printf '[FAIL] %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "请使用 root 执行"
# curl | bash 时恢复交互终端。
if [ ! -t 0 ] && [ -r /dev/tty ]; then exec </dev/tty; fi

printf '极速网络 Apple 签名系统 - 一键部署\n'
printf 'deploy version: %s\n' "$DEPLOY_VERSION"
printf '==================================\n'

read -r -p "站点目录 [${DEFAULT_DIR}]: " APP_DIR || true
APP_DIR="${APP_DIR:-$DEFAULT_DIR}"
read -r -p "域名 [${DEFAULT_DOMAIN}]: " DOMAIN || true
DOMAIN="${DOMAIN:-$DEFAULT_DOMAIN}"

command -v git >/dev/null 2>&1 || die "缺少 git"

# 源码 auto_install.json 指定 PHP 82；composer.json 要求 PHP ^8.2。
# 因此优先使用宝塔 PHP 8.2，仅在不存在时退回其它 >=8.2 PHP。
for p in /www/server/php/82/bin/php /www/server/php/84/bin/php /www/server/php/83/bin/php "$(command -v php 2>/dev/null || true)"; do
    if [ -n "$p" ] && [ -x "$p" ]; then
        if "$p" -r 'exit(version_compare(PHP_VERSION,"8.2.0",">=")?0:1);'; then
            PHP_BIN="$p"
            break
        fi
    fi
done
[ -n "$PHP_BIN" ] || die "未找到 PHP >= 8.2"
PHP_VER="$($PHP_BIN -r 'echo PHP_VERSION;')"
info "PHP: ${PHP_BIN} (${PHP_VER})"
if [[ "$PHP_VER" != 8.2.* ]]; then
    warn "源码 auto_install.json 指定 PHP 8.2；当前使用 ${PHP_VER}，建议宝塔站点最终切换 PHP 8.2。"
fi

# 拉取源码。已有项目不自动 git pull：安装器会写 config/api.php，强拉可能覆盖/冲突安装配置。
if [ -f "$APP_DIR/artisan" ] && [ -f "$APP_DIR/config/api.php" ]; then
    info "检测到已有项目源码，保留现有文件并继续环境配置：$APP_DIR"
elif [ -e "$APP_DIR" ] && [ "$(find "$APP_DIR" -mindepth 1 -maxdepth 1 2>/dev/null | head -n1)" ]; then
    die "目录已存在且不是当前项目：$APP_DIR。请先备份/清理后重试。"
else
    mkdir -p "$(dirname "$APP_DIR")"
    info "从 GitHub 拉取源码..."
    git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi

cd "$APP_DIR"
[ -f composer.json ] || die "缺少 composer.json"
[ -f auto_install.json ] || die "缺少 auto_install.json"
[ -d public ] || die "缺少 public 目录"

# 完全按仓库声明检查依赖：composer.json + auto_install.json。
MISSING_EXT=""
for ext in pdo openssl zip dom opcache fileinfo redis; do
    "$PHP_BIN" -m | grep -qi "^${ext}$" || MISSING_EXT="${MISSING_EXT} ${ext}"
done
[ -z "$MISSING_EXT" ] || die "PHP 缺少源码要求的扩展:${MISSING_EXT}"

DISABLED=",$($PHP_BIN -r 'echo preg_replace("/\\s+/","",ini_get("disable_functions"));'),"
for fn in proc_open pcntl_signal pcntl_alarm symlink; do
    case "$DISABLED" in
        *",${fn},"*) die "PHP 禁用了源码 auto_install.json 要求的函数: ${fn}" ;;
    esac
done
info "PHP 扩展/函数检查通过"

# Composer 依赖。
if command -v composer >/dev/null 2>&1; then
    COMPOSER="$(command -v composer)"
elif [ -x /usr/local/bin/composer ]; then
    COMPOSER="/usr/local/bin/composer"
else
    die "未找到 composer"
fi
info "Composer: $($COMPOSER --version 2>/dev/null | head -n1)"
COMPOSER_ALLOW_SUPERUSER=1 "$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# 源码 config/app.php 的 key/url/name 等直接来自 config/api.php；不使用普通 Laravel APP_KEY 链。
# 因此这里不调用 artisan key:generate，不改写数据库配置，交给项目原生 /install -> /api/install。

# auto_install.json 指定的目录与清理动作。
mkdir -p storage/AppleSignV2 storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod 755 storage/AppleSignV2
chown -R www:www storage bootstrap/cache 2>/dev/null || true
find storage bootstrap/cache -type d -exec chmod 775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +
rm -f index.html 404.html .user.ini

"$PHP_BIN" artisan optimize:clear || die "Laravel 缓存清理失败"

# 宝塔 Nginx：源码 auto_install.json run_path=/public。
NGINX_CONF="/www/server/panel/vhost/nginx/${DOMAIN}.conf"
REWRITE_CONF="/www/server/panel/vhost/rewrite/${DOMAIN}.conf"
if [ -f "$NGINX_CONF" ]; then
    cp -a "$NGINX_CONF" "${NGINX_CONF}.bak.deploy-$(date +%Y%m%d-%H%M%S)"

    # 只替换 server 站点 root 行；当前宝塔站点配置使用标准 root 指令。
    sed -i -E "s#^[[:space:]]*root[[:space:]]+[^;]+;#    root ${APP_DIR}/public;#" "$NGINX_CONF"

    mkdir -p "$(dirname "$REWRITE_CONF")"
    cat > "$REWRITE_CONF" <<'EOF'
location / {
    if (!-e $request_filename){
        rewrite ^(.*)$ /index.php?s=$1 last;
        break;
    }
}
EOF
    info "运行目录已设置为: ${APP_DIR}/public"
    info "伪静态已写入: ${REWRITE_CONF}"

    if command -v nginx >/dev/null 2>&1; then
        nginx -t || die "Nginx 配置检查失败，已保留 .bak.deploy-* 备份"
        if command -v systemctl >/dev/null 2>&1; then
            systemctl reload nginx || /etc/init.d/nginx reload || die "Nginx reload 失败"
        else
            /etc/init.d/nginx reload || die "Nginx reload 失败"
        fi
    else
        warn "未找到 nginx 命令，请在宝塔中手动重载 Nginx"
    fi
else
    warn "未找到宝塔站点配置: ${NGINX_CONF}"
    warn "请将网站运行目录设置为 ${APP_DIR}/public，并配置 Laravel 入口伪静态。"
fi

# 源码安装器自己执行数据库配置/迁移、storage:link、管理员初始化等业务安装步骤。
printf '\n==================================\n'
printf '基础部署完成（源码原生安装流程）\n'
printf '源码目录: %s\n' "$APP_DIR"
printf '运行目录: %s/public\n' "$APP_DIR"
printf 'PHP: %s\n' "$PHP_VER"
printf '数据库配置: 未写入（按源码设计由 /install 页面填写）\n'
printf '下一步访问: http://%s/install\n' "$DOMAIN"
printf '==================================\n'
