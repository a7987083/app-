#!/usr/bin/env bash
set -Eeuo pipefail

DEPLOY_VERSION="1.1.3-ceshi1-remote-baota"
REPO_URL="${REPO_URL:-https://github.com/a7987083/app-.git}"
REPO_BRANCH="${REPO_BRANCH:-main}"
SOURCE_TARBALL="${SOURCE_TARBALL:-}"
PANEL_ROOT="/www/server/panel"
PANEL_PY="${PANEL_PY:-/www/server/panel/pyenv/bin/python3}"
NGINX_BIN="/www/server/nginx/sbin/nginx"

log(){ printf '\033[1;32m[+]\033[0m %s\n' "$*"; }
warn(){ printf '\033[1;33m[!]\033[0m %s\n' "$*" >&2; }
die(){ printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }
cleanup(){ [ -n "${TMP_DIR:-}" ] && rm -rf "$TMP_DIR" || true; }
trap cleanup EXIT

[ "$(id -u)" -eq 0 ] || die "请使用 root 执行"
[ -x "$PANEL_PY" ] || die "未找到宝塔 Python: $PANEL_PY"
[ -x "$NGINX_BIN" ] || die "未找到宝塔 Nginx: $NGINX_BIN"
command -v git >/dev/null 2>&1 || die "未安装 git"
command -v rsync >/dev/null 2>&1 || die "未安装 rsync"

DOMAIN="${1:-${DOMAIN:-}}"
if [ -z "$DOMAIN" ]; then
    read -r -p "请输入域名（例如 ios.zonoeios.xyz）: " DOMAIN
fi
DOMAIN="$(printf '%s' "$DOMAIN" | tr 'A-Z' 'a-z' | tr -d '\r\n' | xargs)"
[ -n "$DOMAIN" ] || die "域名不能为空"
APP_DIR="${APP_DIR:-/www/wwwroot/$DOMAIN}"

PHP_SHORT=""
for v in 74 73 72 71 70; do
    if [ -x "/www/server/php/$v/bin/php" ]; then PHP_SHORT="$v"; break; fi
done
[ -n "$PHP_SHORT" ] || die "源码要求 PHP 7.0~7.4，但宝塔未检测到可用版本"
PHP_BIN="/www/server/php/$PHP_SHORT/bin/php"
PHP_VER="$($PHP_BIN -r 'echo PHP_VERSION;' 2>/dev/null || true)"

TMP_DIR="$(mktemp -d /tmp/ceshi1-deploy.XXXXXX)"
SRC_DIR="$TMP_DIR/src"
mkdir -p "$SRC_DIR"

log "部署版本: $DEPLOY_VERSION"
log "目标域名: $DOMAIN"
log "目标目录: $APP_DIR"
log "选择 PHP: $PHP_VER ($PHP_SHORT)"

if [ -n "$SOURCE_TARBALL" ]; then
    [ -f "$SOURCE_TARBALL" ] || die "SOURCE_TARBALL 不存在: $SOURCE_TARBALL"
    log "从本地源码包解压: $SOURCE_TARBALL"
    tar -xzf "$SOURCE_TARBALL" -C "$SRC_DIR"
else
    log "从 GitHub 获取源码: $REPO_URL ($REPO_BRANCH)"
    git clone --depth 1 --branch "$REPO_BRANCH" "$REPO_URL" "$SRC_DIR" >/dev/null 2>&1 \
      || die "GitHub 源码拉取失败"
    rm -rf "$SRC_DIR/.git"
fi

for f in auto_install.json import.sql application/database.php application/config.php public/index.php public/FRKToHDckx.php vendor/autoload.php; do
    [ -e "$SRC_DIR/$f" ] || die "源码不完整，缺少: $f"
done

$PHP_BIN -r '
$j=json_decode(file_get_contents($argv[1]),true);
if(!$j){fwrite(STDERR,"invalid auto_install.json\n");exit(2);}
if(($j["run_path"]??"")!=="/public"){fwrite(STDERR,"unexpected run_path\n");exit(3);}
if(($j["db_config"]??"")!=="application/database.php"){fwrite(STDERR,"unexpected db_config\n");exit(4);}
' "$SRC_DIR/auto_install.json" || die "auto_install.json 与当前脚本预期不一致，停止部署"

for ext in PDO pdo_mysql; do
    "$PHP_BIN" -r "exit(extension_loaded('$ext')?0:1);" || die "PHP $PHP_VER 缺少扩展: $ext"
done

# 查询宝塔站点及其关联数据库。不同宝塔版本可能无法按站点名命中，目录指纹作为第二续装通道。
PANEL_STATE="$($PANEL_PY - <<PY
import sys
sys.path.insert(0, '$PANEL_ROOT/class')
sys.path.insert(0, '$PANEL_ROOT')
import public
site = public.M('sites').where('name=?', ('$DOMAIN',)).field('id').find()
if not site:
    print('0\t\t\t')
else:
    sid = site.get('id','')
    db = public.M('databases').where('pid=?', (sid,)).field('username,password').find() or {}
    print('1\t%s\t%s\t%s' % (sid, db.get('username',''), db.get('password','')))
PY
)" || PANEL_STATE=$'0\t\t\t'
IFS=$'\t' read -r SITE_EXISTS SITE_ID PANEL_DB_USER PANEL_DB_PASS <<< "$PANEL_STATE"

PROJECT_DIR_READY=0
if [ -f "$APP_DIR/application/database.php" ] \
   && [ -f "$APP_DIR/import.sql" ] \
   && [ -f "$APP_DIR/public/FRKToHDckx.php" ] \
   && [ -f "$APP_DIR/vendor/autoload.php" ]; then
    PROJECT_DIR_READY=1
fi

RESUME=0
if [ "$PROJECT_DIR_READY" = "1" ]; then
    RESUME=1
    warn "检测到本项目已写入目标目录，进入续装模式: $DOMAIN"

    # 优先从已写入的 database.php 读取真实数据库配置；这正是上次失败前写入的配置。
    DB_STATE="$APP_DIR=$APP_DIR "$PHP_BIN" -r '
        $c=include getenv("APP_DIR")."/application/database.php";
        echo ($c["database"]??"")."\t".($c["username"]??"")."\t".($c["password"]??"");
    ')"
    IFS=$'\t' read -r DB_NAME DB_USER DB_PASS <<< "$DB_STATE"

    # 若 database.php 仍是源码占位符，则回退宝塔数据库记录。
    if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ "$DB_NAME" = "BT_DB_NAME" ] || [ "$DB_USER" = "BT_DB_USERNAME" ]; then
        [ -n "$PANEL_DB_USER" ] || die "续装失败：现有 database.php 尚未写入数据库信息，且宝塔未返回关联数据库"
        DB_USER="$PANEL_DB_USER"
        DB_NAME="$PANEL_DB_USER"
        DB_PASS="$PANEL_DB_PASS"
    fi
elif [ "$SITE_EXISTS" = "1" ]; then
    die "宝塔中已存在站点 $DOMAIN，但目标目录不是可识别的本项目；停止以避免覆盖"
fi

if [ "$RESUME" = "0" ]; then
    if [ -e "$APP_DIR" ] && find "$APP_DIR" -mindepth 1 -maxdepth 1 -print -quit | grep -q .; then
        die "目标目录非空且不是可识别的本项目: $APP_DIR；为避免误删，已停止"
    fi

    TOKEN="$(tr -dc 'a-z0-9' </dev/urandom | head -c 8 || true)"
    [ ${#TOKEN} -eq 8 ] || TOKEN="$(date +%s | tail -c 9)"
    DB_USER="z${TOKEN}"
    DB_NAME="$DB_USER"
    DB_PASS="$(tr -dc 'A-Za-z0-9_@#%+=' </dev/urandom | head -c 24 || true)"
    [ ${#DB_PASS} -ge 16 ] || DB_PASS="Zonoe_${TOKEN}_$(date +%s)"

    log "调用宝塔原生 AddSite 创建站点 + MySQL 数据库"
    BT_RESULT="$DOMAIN=$DOMAIN APP_DIR=$APP_DIR PHP_SHORT=$PHP_SHORT DB_USER=$DB_USER DB_PASS=$DB_PASS PANEL_ROOT=$PANEL_ROOT "$PANEL_PY" - <<'PY'
import os,sys,json
panel_root=os.environ.get('PANEL_ROOT','/www/server/panel')
sys.path.insert(0, panel_root + '/class')
sys.path.insert(0, panel_root)
from panelSite import panelSite

domain=os.environ['DOMAIN']; path=os.environ['APP_DIR']; phpver=os.environ['PHP_SHORT']
dbuser=os.environ['DB_USER']; dbpass=os.environ['DB_PASS']
class G(dict):
    __getattr__ = dict.get
    __setattr__ = dict.__setitem__
g=G()
g.webname=json.dumps({'domain':domain,'domainlist':[]})
g.path=path; g.port='80'; g.version=phpver; g.ps='ceshi1 one-click deploy'
g.ftp='false'; g.sql='MySQL'; g.type_id=0; g.type='PHP'; g.project_type='PHP'; g.codeing='utf8mb4'
g.datauser=dbuser; g.datapassword=dbpass; g.ftp_username=''; g.ftp_password=''; g.set_ssl='0'
res=panelSite().AddSite(g)
print(json.dumps(res,ensure_ascii=False))
if not isinstance(res,dict) or not res.get('siteStatus') or not res.get('databaseStatus'):
    raise SystemExit(9)
PY
    )" || die "宝塔 AddSite/数据库创建失败: ${BT_RESULT:-无返回}"
    printf '%s\n' "$BT_RESULT" | tail -1

    DB_REAL="$BT_RESULT" "$PANEL_PY" - <<'PY' > "$TMP_DIR/db-real"
import os,json
lines=[x for x in os.environ.get('BT_RESULT','').splitlines() if x.strip()]
obj=json.loads(lines[-1]) if lines else {}
print('%s\t%s' % (obj.get('databaseUser',''), obj.get('databasePass','')))
PY
    IFS=$'\t' read -r REAL_DB_USER REAL_DB_PASS < "$TMP_DIR/db-real"
    [ -n "$REAL_DB_USER" ] && DB_USER="$REAL_DB_USER"
    [ -n "$REAL_DB_PASS" ] && DB_PASS="$REAL_DB_PASS"
    DB_NAME="$DB_USER"

    for f in .user.ini .htaccess index.html 404.html; do
        chattr -i "$APP_DIR/$f" 2>/dev/null || true
        rm -f "$APP_DIR/$f"
    done

    log "写入 ceshi1 源码"
    rsync -a --delete "$SRC_DIR/" "$APP_DIR/"

    log "写入源码数据库配置"
    DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" APP_DIR="$APP_DIR" "$PANEL_PY" - <<'PY'
import os
from pathlib import Path
p=Path(os.environ['APP_DIR'])/'application/database.php'
s=p.read_text(encoding='utf-8')
for old,new in {
    'BT_DB_NAME': os.environ['DB_NAME'],
    'BT_DB_USERNAME': os.environ['DB_USER'],
    'BT_DB_PASSWORD': os.environ['DB_PASS'],
}.items():
    if old not in s: raise SystemExit('missing placeholder: '+old)
    s=s.replace(old,new)
p.write_text(s,encoding='utf-8')
PY
else
    log "续装：保留现有源码和数据库配置"
fi

MYSQL_BIN=""
for b in /www/server/mysql/bin/mysql /usr/bin/mysql "$(command -v mysql 2>/dev/null || true)"; do
    [ -n "$b" ] && [ -x "$b" ] && { MYSQL_BIN="$b"; break; }
done
[ -n "$MYSQL_BIN" ] || die "未找到 mysql 客户端"

MYSQL_MODE=""
if MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -u"$DB_USER" "$DB_NAME" -Nse 'SELECT 1' >/dev/null 2>&1; then
    MYSQL_MODE="socket"
elif MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -hlocalhost -u"$DB_USER" "$DB_NAME" -Nse 'SELECT 1' >/dev/null 2>&1; then
    MYSQL_MODE="localhost"
elif MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -h127.0.0.1 -u"$DB_USER" "$DB_NAME" -Nse 'SELECT 1' >/dev/null 2>&1; then
    MYSQL_MODE="127.0.0.1"
else
    die "数据库认证失败：账号=$DB_USER 数据库=$DB_NAME；socket/localhost/127.0.0.1 均无法登录"
fi

log "导入源码自带 import.sql（MySQL: $MYSQL_MODE）"
case "$MYSQL_MODE" in
    socket)    MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -u"$DB_USER" "$DB_NAME" < "$APP_DIR/import.sql" ;;
    localhost) MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -hlocalhost -u"$DB_USER" "$DB_NAME" < "$APP_DIR/import.sql" ;;
    127.0.0.1) MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -h127.0.0.1 -u"$DB_USER" "$DB_NAME" < "$APP_DIR/import.sql" ;;
esac

log "设置当前站点 open_basedir"
chattr -i "$APP_DIR/public/.user.ini" 2>/dev/null || true
cat > "$APP_DIR/public/.user.ini" <<EOF
open_basedir=$APP_DIR/:/tmp/
EOF
rm -f "$APP_DIR/.user.ini"

enable_putenv(){
    local ini="$1"
    [ -f "$ini" ] || return 0
    cp -a "$ini" "$ini.deploy-backup-$(date +%Y%m%d%H%M%S)"
    "$PANEL_PY" - "$ini" <<'PY'
import re,sys
p=sys.argv[1]
s=open(p,encoding='utf-8',errors='ignore').read(); lines=[]
for line in s.splitlines(True):
    if re.match(r'^\s*disable_functions\s*=', line):
        k,v=line.split('=',1)
        funcs=[x.strip() for x in v.strip().split(',') if x.strip() and x.strip()!='putenv']
        line=k+'= '+','.join(funcs)+'\n'
    lines.append(line)
open(p,'w',encoding='utf-8').writelines(lines)
PY
}
enable_putenv "/www/server/php/$PHP_SHORT/etc/php.ini"
enable_putenv "/www/server/php/$PHP_SHORT/etc/php-cli.ini"

chown -R www:www "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
chmod -R 775 "$APP_DIR/runtime"
chmod 755 "$APP_DIR/public/index.php" "$APP_DIR/public/FRKToHDckx.php"

VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
REWRITE="/www/server/panel/vhost/rewrite/$DOMAIN.conf"
[ -f "$VHOST" ] || die "宝塔未生成 Nginx vhost: $VHOST"
mkdir -p "$(dirname "$REWRITE")"

log "设置运行目录 /public"
cp -a "$VHOST" "$VHOST.deploy-backup-$(date +%Y%m%d%H%M%S)"
APP_DIR="$APP_DIR" "$PANEL_PY" - "$VHOST" <<'PY'
import os,re,sys
p=sys.argv[1]; root=os.environ['APP_DIR'].rstrip('/')+'/public'
s=open(p,encoding='utf-8',errors='ignore').read()
s2,n=re.subn(r'(?m)^(\s*root\s+)[^;]+;', lambda m:m.group(1)+root+';', s, count=1)
if n!=1: raise SystemExit('cannot locate nginx root directive')
open(p,'w',encoding='utf-8').write(s2)
PY

cat > "$REWRITE" <<'EOF'
location / {
    if (!-e $request_filename) {
        rewrite ^(.*)$ /index.php?s=$1 last;
        break;
    }
}
EOF

if [ -x "/etc/init.d/php-fpm-$PHP_SHORT" ]; then
    "/etc/init.d/php-fpm-$PHP_SHORT" reload 2>/dev/null || "/etc/init.d/php-fpm-$PHP_SHORT" restart
else
    warn "未找到 /etc/init.d/php-fpm-$PHP_SHORT，请在宝塔面板中重载 PHP $PHP_VER"
fi

"$NGINX_BIN" -t || die "Nginx 配置检测失败；vhost 备份保留在 $VHOST.deploy-backup-*"
"$NGINX_BIN" -s reload

HTTP_CODE="$(curl -sS -o "$TMP_DIR/http.out" -w '%{http_code}' -H "Host: $DOMAIN" http://127.0.0.1/ || true)"
if [ "$HTTP_CODE" = "000" ] || [ "$HTTP_CODE" = "404" ]; then
    warn "本机 HTTP 验证返回 $HTTP_CODE"
    [ -f "/www/wwwlogs/$DOMAIN.error.log" ] && tail -30 "/www/wwwlogs/$DOMAIN.error.log" >&2 || true
else
    log "本机 HTTP 验证: $HTTP_CODE"
fi

printf '\n=================================\n'
printf 'ceshi1 一键部署完成\n'
printf 'deploy version: %s\n' "$DEPLOY_VERSION"
printf '域名: http://%s\n' "$DOMAIN"
printf '源码目录: %s\n' "$APP_DIR"
printf '运行目录: %s/public\n' "$APP_DIR"
printf 'PHP: %s\n' "$PHP_VER"
printf '数据库: %s\n' "$DB_NAME"
printf '数据库用户: %s\n' "$DB_USER"
printf '数据库密码: %s\n' "$DB_PASS"
printf '后台入口: http://%s/FRKToHDckx.php\n' "$DOMAIN"
printf '后台账号: admin\n'
printf '后台初始密码: 123456\n'
printf '=================================\n'
warn "首次登录后立即修改后台默认密码；脚本不自动申请 SSL。"
