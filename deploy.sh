#!/usr/bin/env bash
set -Eeuo pipefail

REPO_URL="https://github.com/a7987083/app-.git"
BRANCH="main"
DEFAULT_DIR="/www/wwwroot/app3.zonoeios.xyz"

C_RESET='\033[0m'; C_GREEN='\033[32m'; C_YELLOW='\033[33m'; C_RED='\033[31m'
info(){ printf "%b[INFO]%b %s\n" "$C_GREEN" "$C_RESET" "$*"; }
warn(){ printf "%b[WARN]%b %s\n" "$C_YELLOW" "$C_RESET" "$*"; }
die(){ printf "%b[FAIL]%b %s\n" "$C_RED" "$C_RESET" "$*" >&2; exit 1; }
trap 'die "第 $LINENO 行执行失败。请保留上方错误输出。"' ERR

[ "$(id -u)" -eq 0 ] || die "请使用 root 执行。"

printf '\n极速网络 Apple 签名系统 - 一键部署\n==================================\n'
read -r -p "站点目录 [${DEFAULT_DIR}]: " APP_DIR
APP_DIR="${APP_DIR:-$DEFAULT_DIR}"
read -r -p "域名 [app3.zonoeios.xyz]: " DOMAIN
DOMAIN="${DOMAIN:-app3.zonoeios.xyz}"

command -v git >/dev/null || die "缺少 git，请先安装 git。"

# Prefer BT PHP 8.2/8.4 binaries, then PATH php.
PHP_BIN=""
for p in /www/server/php/82/bin/php /www/server/php/84/bin/php "$(command -v php 2>/dev/null || true)"; do
  [ -n "$p" ] && [ -x "$p" ] && PHP_BIN="$p" && break
done
[ -n "$PHP_BIN" ] || die "未找到 PHP。项目要求 PHP >= 8.2。"
PHP_VER="$($PHP_BIN -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
$PHP_BIN -r 'exit(version_compare(PHP_VERSION,"8.2.0",">=")?0:1);' || die "PHP ${PHP_VER} 过低，项目要求 >= 8.2。"
info "PHP: $PHP_BIN ($PHP_VER)"

# Source checkout/update. Existing config/api.php is preserved because it may contain a completed installation.
if [ -d "$APP_DIR/.git" ]; then
  info "更新现有源码：$APP_DIR"
  git -C "$APP_DIR" fetch origin "$BRANCH"
  git -C "$APP_DIR" checkout "$BRANCH"
  git -C "$APP_DIR" pull --ff-only origin "$BRANCH"
elif [ -e "$APP_DIR" ] && [ "$(find "$APP_DIR" -mindepth 1 -maxdepth 1 2>/dev/null | head -n1)" ]; then
  warn "$APP_DIR 已存在且非空，不覆盖现有文件。"
  read -r -p "确认这里已经是 app- 源码并继续？[y/N]: " OK
  [[ "$OK" =~ ^[Yy]$ ]] || exit 1
else
  mkdir -p "$(dirname "$APP_DIR")"
  git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi
cd "$APP_DIR"
[ -f artisan ] && [ -f composer.json ] && [ -f config/api.php ] || die "目录不是有效的 app- Laravel 源码。"

# Composer: use installed composer first; otherwise download official installer after signature verification.
COMPOSER=""
if command -v composer >/dev/null 2>&1; then
  COMPOSER="$(command -v composer)"
elif [ -x /usr/local/bin/composer ]; then
  COMPOSER=/usr/local/bin/composer
else
  info "安装 Composer..."
  command -v curl >/dev/null || die "缺少 curl。"
  EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  ACTUAL="$($PHP_BIN -r "echo hash_file('sha384','/tmp/composer-setup.php');")"
  [ "$EXPECTED" = "$ACTUAL" ] || die "Composer installer 校验失败。"
  $PHP_BIN /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
  COMPOSER=/usr/local/bin/composer
fi
info "Composer: $($COMPOSER --version 2>/dev/null | head -n1)"

# Check extensions required by composer.json + auto_install.json.
MISSING=""
for ext in pdo openssl zip dom fileinfo; do
  $PHP_BIN -m | grep -qi "^${ext}$" || MISSING="$MISSING $ext"
done
[ -z "$MISSING" ] || die "PHP 缺少扩展:$MISSING。请在宝塔 PHP 扩展中安装后重跑。"
if ! $PHP_BIN -m | grep -qi '^redis$'; then
  warn "未检测到 redis 扩展；仓库 auto_install.json 要求 redis。涉及 Redis 功能前请安装。"
fi

# Repository explicitly requires these functions for installation/runtime.
DISABLED="$($PHP_BIN -r 'echo ini_get("disable_functions");')"
for fn in proc_open pcntl_signal pcntl_alarm symlink; do
  if printf ',%s,' "$DISABLED" | grep -q ",$fn,"; then
    die "PHP 禁用了 $fn。仓库 auto_install.json 要求启用它，请在宝塔 PHP 禁用函数中移除后重跑。"
  fi
done

# Keep environment minimal. This project stores app/mysql settings in config/api.php via its own /install flow.
if [ ! -f .env ]; then
  if [ -f .env.example ]; then cp .env.example .env; else : > .env; fi
fi
set_env(){
  local key="$1" value="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "http://${DOMAIN}"

info "安装 Composer 依赖..."
COMPOSER_ALLOW_SUPERUSER=1 "$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Ensure APP_KEY exists. Do not overwrite a valid existing key.
APP_KEY="$($PHP_BIN -r '$p=".env"; $s=@file_get_contents($p); if(preg_match("/^APP_KEY=(.*)$/m",$s,$m)) echo trim($m[1]);')"
if [ -z "$APP_KEY" ]; then
  NEW_KEY="base64:$($PHP_BIN -r 'echo base64_encode(random_bytes(32));')"
  set_env APP_KEY "$NEW_KEY"
  info "已生成 APP_KEY。"
fi

mkdir -p storage/AppleSignV2 storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www:www "$APP_DIR" 2>/dev/null || true
find storage bootstrap/cache -type d -exec chmod 775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +
chmod 755 storage/AppleSignV2

$PHP_BIN artisan optimize:clear || true

# storage:link may already exist; keep deployment idempotent.
if [ ! -e public/storage ]; then
  $PHP_BIN artisan storage:link || warn "storage:link 未成功，可在安装后重新执行。"
fi

printf '\n%b基础部署完成。%b\n' "$C_GREEN" "$C_RESET"
printf '项目目录: %s\n' "$APP_DIR"
printf '网站运行目录必须指向: %s/public\n' "$APP_DIR"
printf 'PHP: %s\n' "$PHP_VER"
printf '\n重要：本项目的 MySQL 参数不是普通 DB_* 读取链。\n'
printf 'config/database.php 从 config(api.mysql.*) 读取，仓库自带 /install 页面负责写入 config/api.php 并执行安装。\n'
printf '因此脚本不会猜测或覆盖数据库/管理员/授权配置。\n\n'
printf '下一步访问：http://%s/install\n' "$DOMAIN"
printf '如果 /install 仍返回 Nginx 404，那是站点 PHP-FPM/Nginx 层问题，与 Laravel 安装器无关。\n'
