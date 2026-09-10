#!/usr/bin/env bash
set -Eeuo pipefail

REPO_URL="https://github.com/a7987083/app-.git"
BRANCH="main"
DEFAULT_DIR="/www/wwwroot/app3.zonoeios.xyz"
PHP_BIN=""
COMPOSER=""
APP_DIR=""
DOMAIN=""

C_GREEN='\033[32m'; C_RESET='\033[0m'; C_RED='\033[31m'
info(){ echo -e "${C_GREEN}[INFO]${C_RESET} $*"; }
die(){ echo -e "${C_RED}[FAIL]${C_RESET} $*"; exit 1; }

[ "$(id -u)" = 0 ] || die "请使用 root 执行"

echo "极速网络 Apple 签名系统 - 一键部署"
echo "=================================="

read -r -p "站点目录 [${DEFAULT_DIR}]: " APP_DIR
APP_DIR="${APP_DIR:-$DEFAULT_DIR}"
read -r -p "域名 [app3.zonoeios.xyz]: " DOMAIN
DOMAIN="${DOMAIN:-app3.zonoeios.xyz}"

for p in /www/server/php/82/bin/php /www/server/php/84/bin/php "$(command -v php 2>/dev/null || true)"; do
    if [ -n "$p" ] && [ -x "$p" ]; then
        PHP_BIN="$p"
        break
    fi
done

[ -n "$PHP_BIN" ] || die "未找到 PHP 8.2+"
info "PHP: $PHP_BIN"

if [ -d "$APP_DIR/.git" ]; then
    git -C "$APP_DIR" pull --ff-only origin "$BRANCH"
elif [ ! -e "$APP_DIR" ]; then
    mkdir -p "$(dirname "$APP_DIR")"
    git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi

cd "$APP_DIR"
[ -f artisan ] || die "不是有效 Laravel 项目"
[ -f config/api.php ] || die "缺少 config/api.php"

if command -v composer >/dev/null; then
    COMPOSER="$(command -v composer)"
else
    die "未找到 composer，请先安装 composer"
fi

info "安装依赖"
COMPOSER_ALLOW_SUPERUSER=1 "$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

if [ ! -f .env ]; then
    cp .env.example .env 2>/dev/null || touch .env
fi

php_key=$(grep '^APP_KEY=' .env | cut -d= -f2- || true)
if [ -z "$php_key" ]; then
    "$PHP_BIN" artisan key:generate --force
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www:www storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache

"$PHP_BIN" artisan optimize:clear

if [ ! -e public/storage ]; then
    "$PHP_BIN" artisan storage:link || true
fi

echo ""
echo "部署基础环境完成"
echo "访问: http://${DOMAIN}/install"
echo "注意：数据库继续使用项目官方 /install 流程写入 config/api.php"
