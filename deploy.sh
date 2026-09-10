#!/usr/bin/env bash
set -Eeuo pipefail

DEPLOY_VERSION="1.2.0-source-flow-ext-auto"
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
# 优先使用宝塔 PHP 8.2，仅在不存在时退回其它 >=8.2 PHP。
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
PHP_PREFIX="$(cd "$(dirname "$PHP_BIN")/.." && pwd)"
PHP_SHORT="$(basename "$PHP_PREFIX")"
PHP_INI="$($PHP_BIN --ini 2>/dev/null | awk -F': ' '/Loaded Configuration File/{print $2; exit}')"
EXT_DIR="$($PHP_BIN -r 'echo ini_get("extension_dir");')"

info "PHP: ${PHP_BIN} (${PHP_VER})"
info "PHP prefix: ${PHP_PREFIX}"
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

php_ext_loaded() {
    "$PHP_BIN" -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$1"
}

reload_php_fpm() {
    local init_script="/etc/init.d/php-fpm-${PHP_SHORT}"
    if [ -x "$init_script" ]; then
        "$init_script" reload >/dev/null 2>&1 || "$init_script" restart >/dev/null 2>&1 || return 1
        return 0
    fi
    if command -v systemctl >/dev/null 2>&1; then
        systemctl reload "php-fpm-${PHP_SHORT}" >/dev/null 2>&1 || systemctl restart "php-fpm-${PHP_SHORT}" >/dev/null 2>&1 || true
    fi
    return 0
}

enable_existing_extension() {
    local ext="$1"
    local so="${EXT_DIR}/${ext}.so"
    local directive

    [ -f "$so" ] || return 1
    [ -n "$PHP_INI" ] && [ "$PHP_INI" != "(none)" ] && [ -f "$PHP_INI" ] || return 1

    if [ "$ext" = "opcache" ]; then
        directive="zend_extension=${so}"
    else
        directive="extension=${so}"
    fi

    if ! grep -Fq "$so" "$PHP_INI"; then
        {
            printf '\n; zonoe deploy: enable %s\n' "$ext"
            printf '%s\n' "$directive"
        } >> "$PHP_INI"
    fi

    reload_php_fpm || true
    php_ext_loaded "$ext"
}

bt_install_extension() {
    local ext="$1"
    local installer="/www/server/panel/install/install_soft.sh"

    [ -f "$installer" ] || return 1

    info "尝试通过宝塔扩展安装器安装 PHP ${PHP_SHORT} 的 ${ext} ..."
    if /bin/bash "$installer" 1 install "$ext" "$PHP_SHORT"; then
        reload_php_fpm || true
        if php_ext_loaded "$ext"; then
            return 0
        fi
    fi
    return 1
}

ensure_auto_extension() {
    local ext="$1"

    if php_ext_loaded "$ext"; then
        info "PHP 扩展 ${ext}: OK"
        return 0
    fi

    warn "PHP 扩展 ${ext} 缺失，开始自动处理"

    # 优先启用当前 PHP 已存在的模块，避免重复下载安装。
    if enable_existing_extension "$ext"; then
        info "PHP 扩展 ${ext}: 已启用现有模块"
        return 0
    fi

    # 再调用宝塔自身的 PHP 扩展安装链，并绑定到当前实际 PHP 版本。
    if bt_install_extension "$ext"; then
        info "PHP 扩展 ${ext}: 自动安装成功"
        return 0
    fi

    die "PHP 扩展 ${ext} 自动安装失败。未改用系统 PHP 包，避免装到错误 PHP 版本。"
}

# auto_install.json 明确声明需要的扩展：opcache,fileinfo,redis。
# 缺失时自动启用/安装，并在安装后重新验证。
for ext in opcache fileinfo redis; do
    ensure_auto_extension "$ext"
done

# composer.json 的其它基础扩展继续严格验证。
# 它们不在 auto_install.json 的自动扩展列表中，不猜测当前 PHP 编译方式。
MISSING_CORE=""
for ext in pdo openssl zip dom; do
    php_ext_loaded "$ext" || MISSING_CORE="${MISSING_CORE} ${ext}"
done
[ -z "$MISSING_CORE" ] || die "PHP 缺少 composer.json 要求的基础扩展:${MISSING_CORE}。这些不在源码 auto_install.json 的自动安装列表中。"

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

# 源码 config/app.php 的 key/url/name 等直接来自 config/api.php；不使用普通 Laravel APP_KEY / DB_* 链。
# 数据库配置、迁移、storage:link、管理员初始化交给项目原生 /install -> /api/install。

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

printf '\n==================================\n'
printf '基础部署完成（源码原生安装流程）\n'
printf 'deploy version: %s\n' "$DEPLOY_VERSION"
printf '源码目录: %s\n' "$APP_DIR"
printf '运行目录: %s/public\n' "$APP_DIR"
printf 'PHP: %s\n' "$PHP_VER"
printf 'PHP扩展: opcache/fileinfo/redis 已验证\n'
printf '数据库配置: 未写入（按源码设计由 /install 页面填写）\n'
printf '下一步访问: http://%s/install\n' "$DOMAIN"
printf '==================================\n'
