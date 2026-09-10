#!/usr/bin/env bash
set -Eeuo pipefail

INSTALLER_VERSION="1.0.0"
REPO_OWNER="${REPO_OWNER:-a7987083}"
REPO_NAME="${REPO_NAME:-app-}"
REPO_BRANCH="${REPO_BRANCH:-main}"
RAW_BASE="https://raw.githubusercontent.com/${REPO_OWNER}/${REPO_NAME}/${REPO_BRANCH}"

log(){ printf '\033[1;32m[+]\033[0m %s\n' "$*"; }
warn(){ printf '\033[1;33m[!]\033[0m %s\n' "$*" >&2; }
die(){ printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "请使用 root 执行"

if command -v curl >/dev/null 2>&1; then
    FETCH=(curl -fsSL --connect-timeout 10 --retry 2)
elif command -v wget >/dev/null 2>&1; then
    FETCH=(wget -qO- --timeout=10 --tries=2)
else
    die "未检测到 curl 或 wget，请先安装其中一个"
fi

printf '\n========================================\n'
printf ' 软件源远程一键部署安装器 v%s\n' "$INSTALLER_VERSION"
printf ' Repository: %s/%s\n' "$REPO_OWNER" "$REPO_NAME"
printf ' Branch: %s\n' "$REPO_BRANCH"
printf '========================================\n\n'

DOMAIN="${DOMAIN:-}"
if [ -z "$DOMAIN" ]; then
    read -r -p "请输入部署域名（例如 ios.example.com）: " DOMAIN
fi
DOMAIN="$(printf '%s' "$DOMAIN" | tr 'A-Z' 'a-z' | xargs)"
[[ "$DOMAIN" =~ ^([a-z0-9-]+\.)+[a-z0-9-]+$ ]] || die "域名格式不正确: $DOMAIN"

ADMIN_USER="${ADMIN_USER:-}"
if [ -z "$ADMIN_USER" ]; then
    read -r -p "请输入后台用户名 [admin]: " ADMIN_USER
    ADMIN_USER="${ADMIN_USER:-admin}"
fi
[[ "$ADMIN_USER" =~ ^[A-Za-z0-9_.-]{3,20}$ ]] || die "后台用户名仅允许 3-20 位字母、数字、点、下划线、横线"

ADMIN_PASS="${ADMIN_PASS:-}"
if [ -z "$ADMIN_PASS" ]; then
    read -r -s -p "请输入后台密码（至少 8 位，直接回车则自动生成）: " ADMIN_PASS
    printf '\n'
fi
if [ -z "$ADMIN_PASS" ]; then
    if command -v openssl >/dev/null 2>&1; then
        ADMIN_PASS="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9_@#%+=' | head -c 18)"
    else
        ADMIN_PASS="Zonoe_$(date +%s)_$RANDOM"
    fi
    log "已自动生成后台密码"
fi
[ "${#ADMIN_PASS}" -ge 8 ] || die "后台密码至少 8 位"

APP_DIR="${APP_DIR:-/www/wwwroot/$DOMAIN}"
TMP_DIR="$(mktemp -d /tmp/app-source-bootstrap.XXXXXX)"
trap 'rm -rf "$TMP_DIR"' EXIT
DEPLOY_FILE="$TMP_DIR/deploy.sh"

log "下载远程部署脚本: ${RAW_BASE}/deploy.sh"
"${FETCH[@]}" "${RAW_BASE}/deploy.sh" > "$DEPLOY_FILE" || die "deploy.sh 下载失败"
[ -s "$DEPLOY_FILE" ] || die "deploy.sh 内容为空"
grep -q '^#!/usr/bin/env bash' "$DEPLOY_FILE" || die "deploy.sh 格式校验失败"
chmod 700 "$DEPLOY_FILE"

log "开始部署站点"
DOMAIN="$DOMAIN" APP_DIR="$APP_DIR" REPO_BRANCH="$REPO_BRANCH" bash "$DEPLOY_FILE"

[ -f "$APP_DIR/application/database.php" ] || die "部署完成后未找到数据库配置: $APP_DIR/application/database.php"

PHP_BIN=""
for v in 74 73 72 71 70; do
    if [ -x "/www/server/php/$v/bin/php" ]; then
        PHP_BIN="/www/server/php/$v/bin/php"
        break
    fi
done
[ -n "$PHP_BIN" ] || die "未找到 PHP 7.0-7.4，无法更新后台账号"

log "替换默认后台凭证"
ADMIN_USER="$ADMIN_USER" ADMIN_PASS="$ADMIN_PASS" APP_DIR="$APP_DIR" "$PHP_BIN" <<'PHP'
<?php
$appDir = getenv('APP_DIR');
$user = getenv('ADMIN_USER');
$pass = getenv('ADMIN_PASS');
$config = include $appDir . '/application/database.php';
$host = isset($config['hostname']) ? $config['hostname'] : '127.0.0.1';
$port = isset($config['hostport']) ? $config['hostport'] : '3306';
$db   = $config['database'];
$dbu  = $config['username'];
$dbp  = $config['password'];
$prefix = isset($config['prefix']) ? $config['prefix'] : 'fa_';

$salt = substr(bin2hex(random_bytes(8)), 0, 6);
$hash = md5(md5($pass) . $salt);

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $dbu,
    $dbp,
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);
$table = $prefix . 'admin';
$sql = "UPDATE `{$table}` SET username=:username,password=:password,salt=:salt,loginfailure=0,token='' WHERE id=1";
$stmt = $pdo->prepare($sql);
$stmt->execute(array(':username'=>$user, ':password'=>$hash, ':salt'=>$salt));
if ($stmt->rowCount() < 1) {
    $check = $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE id=1")->fetchColumn();
    if (!$check) {
        fwrite(STDERR, "管理员记录 id=1 不存在\n");
        exit(12);
    }
}
PHP

printf '\n========================================\n'
printf ' 远程一键部署完成\n'
printf ' 域名: http://%s\n' "$DOMAIN"
printf ' 后台入口: http://%s/FRKToHDckx.php\n' "$DOMAIN"
printf ' 后台用户名: %s\n' "$ADMIN_USER"
printf ' 后台密码: %s\n' "$ADMIN_PASS"
printf ' 源码目录: %s\n' "$APP_DIR"
printf ' Repository: https://github.com/%s/%s\n' "$REPO_OWNER" "$REPO_NAME"
printf ' Branch: %s\n' "$REPO_BRANCH"
printf '========================================\n'
warn "deploy.sh 中间打印的 admin/123456 已被本安装器立即替换；以上最终凭证为准。"
warn "当前脚本不自动申请 SSL；确认 HTTP 正常后再在宝塔申请证书。"
