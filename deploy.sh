#!/usr/bin/env bash
set -Eeuo pipefail

DEPLOY_VERSION="1.0.2"
REPO_URL="https://github.com/a7987083/app-.git"
BRANCH="main"
DEFAULT_DIR="/www/wwwroot/app3.zonoeios.xyz"
PHP_BIN=""
COMPOSER=""
APP_DIR=""
DOMAIN=""

info(){ echo "[INFO] $*"; }
die(){ echo "[FAIL] $*"; exit 1; }

[ "$(id -u)" = 0 ] || die "请使用 root 执行"
[ -t 0 ] || exec </dev/tty

echo "极速网络 Apple 签名系统 - 一键部署"
echo "deploy version: ${DEPLOY_VERSION}"

read -r -p "站点目录 [${DEFAULT_DIR}]: " APP_DIR
APP_DIR="${APP_DIR:-$DEFAULT_DIR}"
read -r -p "域名 [app3.zonoeios.xyz]: " DOMAIN
DOMAIN="${DOMAIN:-app3.zonoeios.xyz}"

for p in /www/server/php/84/bin/php /www/server/php/82/bin/php "$(command -v php 2>/dev/null || true)"; do
    if [ -n "$p" ] && [ -x "$p" ]; then PHP_BIN="$p"; break; fi
done
[ -n "$PHP_BIN" ] || die "未找到 PHP"
info "PHP: $PHP_BIN"

if [ ! -f "$APP_DIR/artisan" ]; then
    [ -e "$APP_DIR" ] && [ "$(find "$APP_DIR" -mindepth 1 -maxdepth 1 2>/dev/null | wc -l)" != "0" ] && die "目录存在但不是 Laravel 项目，请清理后重试"
    mkdir -p "$(dirname "$APP_DIR")"
    git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi

cd "$APP_DIR"
[ -f artisan ] || die "不是有效 Laravel 项目"
[ -f config/api.php ] || die "缺少 config/api.php"

command -v composer >/dev/null || die "未找到 composer"
COMPOSER=$(command -v composer)
COMPOSER_ALLOW_SUPERUSER=1 "$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

[ -f .env ] || cp .env.example .env 2>/dev/null || touch .env
grep -q '^APP_KEY=' .env || echo 'APP_KEY=' >> .env
"$PHP_BIN" artisan key:generate --force || true

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www:www storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache
"$PHP_BIN" artisan optimize:clear

if [ ! -e public/storage ]; then
    "$PHP_BIN" artisan storage:link
fi

REWRITE="/www/server/panel/vhost/rewrite/${DOMAIN}.conf"
mkdir -p "$(dirname "$REWRITE")"
cat > "$REWRITE" <<'EOF'
location / {
    if (!-e $request_filename){
        rewrite ^(.*)$ /index.php?s=$1 last;
        break;
    }
}
EOF

NGINX="/www/server/panel/vhost/nginx/${DOMAIN}.conf"
if [ -f "$NGINX" ]; then
    sed -i "s#root .*;#root ${APP_DIR}/public;#" "$NGINX"
fi

nginx -t && systemctl reload nginx || true

echo ""
echo "部署完成"
echo "运行目录: ${APP_DIR}/public"
echo "访问: http://${DOMAIN}/install"
