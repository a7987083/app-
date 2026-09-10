#!/usr/bin/env bash
set -Eeuo pipefail

DEPLOY_VERSION="3.0.0-auto-install-native-baota"
REPO_URL="${REPO_URL:-https://github.com/a7987083/app-.git}"
REPO_BRANCH="${REPO_BRANCH:-main}"
SOURCE_TARBALL="${SOURCE_TARBALL:-}"
PANEL_ROOT="${PANEL_ROOT:-/www/server/panel}"
PANEL_PY="${PANEL_PY:-/www/server/panel/pyenv/bin/python3}"
NGINX_BIN="${NGINX_BIN:-/www/server/nginx/sbin/nginx}"

log(){ printf '\033[1;32m[+]\033[0m %s\n' "$*"; }
warn(){ printf '\033[1;33m[!]\033[0m %s\n' "$*" >&2; }
die(){ printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }
cleanup(){ [ -n "${TMP_DIR:-}" ] && rm -rf "$TMP_DIR" || true; }
trap cleanup EXIT

[ "$(id -u)" -eq 0 ] || die "请使用 root 执行"
[ -x "$PANEL_PY" ] || die "未找到宝塔 Python: $PANEL_PY"
[ -x "$NGINX_BIN" ] || die "未找到宝塔 Nginx: $NGINX_BIN"
[ -f "$PANEL_ROOT/class/panelSite.py" ] || die "未找到宝塔 panelSite.py"
command -v git >/dev/null 2>&1 || die "未安装 git"
command -v rsync >/dev/null 2>&1 || die "未安装 rsync"

DOMAIN="${1:-${DOMAIN:-}}"
if [ -z "$DOMAIN" ]; then
    read -r -p "请输入域名（例如 ios.zonoeios.xyz）: " DOMAIN
fi
DOMAIN="$(printf '%s' "$DOMAIN" | tr 'A-Z' 'a-z' | tr -d '\r\n' | xargs)"
[ -n "$DOMAIN" ] || die "域名不能为空"
APP_DIR="${APP_DIR:-/www/wwwroot/$DOMAIN}"

TMP_DIR="$(mktemp -d /tmp/app-source-deploy.XXXXXX)"
SRC_DIR="$TMP_DIR/src"
mkdir -p "$SRC_DIR"

log "部署版本: $DEPLOY_VERSION"
log "目标域名: $DOMAIN"
log "目标目录: $APP_DIR"

if [ -n "$SOURCE_TARBALL" ]; then
    [ -f "$SOURCE_TARBALL" ] || die "SOURCE_TARBALL 不存在: $SOURCE_TARBALL"
    log "从本地源码包解压: $SOURCE_TARBALL"
    tar -xzf "$SOURCE_TARBALL" -C "$SRC_DIR"
else
    log "从 GitHub 获取源码: $REPO_URL ($REPO_BRANCH)"
    git clone --depth 1 --branch "$REPO_BRANCH" "$REPO_URL" "$SRC_DIR" >/dev/null 2>&1 || die "GitHub 源码拉取失败"
    rm -rf "$SRC_DIR/.git"
fi

for f in auto_install.json import.sql application/database.php application/config.php public/index.php public/FRKToHDckx.php vendor/autoload.php; do
    [ -e "$SRC_DIR/$f" ] || die "源码不完整，缺少: $f"
done

META="$TMP_DIR/meta.env"
python3 - "$SRC_DIR/auto_install.json" > "$META" <<'PY'
import json,sys,shlex
p=sys.argv[1]
with open(p,encoding='utf-8') as f:
    j=json.load(f)
def emit(k,v):
    print('%s=%s' % (k, shlex.quote(str(v))))
emit('PHP_VERSIONS', j.get('php_versions',''))
emit('DB_CONFIG', j.get('db_config','application/database.php'))
emit('RUN_PATH', j.get('run_path','/public'))
emit('SUCCESS_URL', j.get('success_url','/'))
emit('ADMIN_USERNAME', j.get('admin_username','admin'))
emit('ADMIN_PASSWORD', j.get('admin_password','123456'))
emit('REMOVE_FILES', '\n'.join(j.get('remove_file',[]) or []))
emit('ENABLE_FUNCTIONS', '\n'.join(j.get('enable_functions',[]) or []))
emit('PHP_EXT', j.get('php_ext',''))
PY
. "$META"

[ -n "$PHP_VERSIONS" ] || die "auto_install.json 未声明 php_versions"
[ -n "$DB_CONFIG" ] || die "auto_install.json 未声明 db_config"
[ -n "$RUN_PATH" ] || die "auto_install.json 未声明 run_path"

PHP_SHORT=""
IFS=',' read -r -a PHP_CANDIDATES <<< "$PHP_VERSIONS"
for ((i=${#PHP_CANDIDATES[@]}-1; i>=0; i--)); do
    v="${PHP_CANDIDATES[$i]}"
    if [ -x "/www/server/php/$v/bin/php" ]; then PHP_SHORT="$v"; break; fi
done
[ -n "$PHP_SHORT" ] || die "未检测到 auto_install.json 支持的 PHP 版本: $PHP_VERSIONS"
PHP_BIN="/www/server/php/$PHP_SHORT/bin/php"
PHP_VER="$($PHP_BIN -r 'echo PHP_VERSION;' 2>/dev/null || true)"
log "选择 PHP: $PHP_VER ($PHP_SHORT)"
log "运行目录: $RUN_PATH"
log "数据库配置: $DB_CONFIG"

for ext in PDO pdo_mysql; do
    "$PHP_BIN" -r "exit(extension_loaded('$ext')?0:1);" || die "PHP $PHP_VER 缺少扩展: $ext"
done

PROJECT_DIR=0
if [ -f "$APP_DIR/$DB_CONFIG" ] && [ -f "$APP_DIR/import.sql" ] && [ -f "$APP_DIR/public/FRKToHDckx.php" ] && [ -f "$APP_DIR/vendor/autoload.php" ]; then
    PROJECT_DIR=1
fi

UNKNOWN_ENTRY=""
if [ -d "$APP_DIR" ] && [ "$PROJECT_DIR" = "0" ]; then
    UNKNOWN_ENTRY="$(find "$APP_DIR" -mindepth 1 -maxdepth 1 ! -name '.user.ini' ! -name 'index.html' ! -name '404.html' -print -quit 2>/dev/null || true)"
fi
[ -z "$UNKNOWN_ENTRY" ] || die "目标目录已有非本项目文件: $UNKNOWN_ENTRY；停止以避免覆盖"

SITE_STATE="$TMP_DIR/site-state"
DOMAIN="$DOMAIN" APP_DIR="$APP_DIR" PANEL_ROOT="$PANEL_ROOT" "$PANEL_PY" - <<'PY' > "$SITE_STATE"
import os,sys
panel=os.environ['PANEL_ROOT']; cls=panel+'/class'
sys.path.insert(0,panel); sys.path.insert(0,cls); os.chdir(panel)
import public
domain=os.environ['DOMAIN']; path=os.environ['APP_DIR'].rstrip('/')
sid=public.M('sites').where('name=?',(domain,)).getField('id')
if sid:
    spath=public.M('sites').where('id=?',(sid,)).getField('path') or ''
    print('1\t%s\t%s' % (sid,spath))
else:
    print('0\t\t')
PY
IFS=$'\t' read -r SITE_EXISTS SITE_ID SITE_PATH < "$SITE_STATE"

if [ "$SITE_EXISTS" = "1" ]; then
    [ -z "$SITE_PATH" ] || [ "$SITE_PATH" = "$APP_DIR" ] || die "宝塔中域名 $DOMAIN 已绑定到其他目录: $SITE_PATH"
    log "宝塔网站已存在: id=$SITE_ID"
else
    [ "$PROJECT_DIR" = "1" ] && warn "检测到本项目半成品目录，但宝塔网站列表无记录；现在补登记"
    log "使用宝塔原生 AddSite 创建普通 PHP 网站"
    SITE_RESULT="$TMP_DIR/site-result"
    DOMAIN="$DOMAIN" APP_DIR="$APP_DIR" PHP_SHORT="$PHP_SHORT" PANEL_ROOT="$PANEL_ROOT" "$PANEL_PY" - <<'PY' > "$SITE_RESULT"
import json,os,sys,traceback
panel=os.environ['PANEL_ROOT']; cls=panel+'/class'
sys.path.insert(0,panel); sys.path.insert(0,cls); os.chdir(panel)
import public
from panelSite import panelSite
class Get(dict):
    def __getattr__(self,key):
        if key in self: return self[key]
        raise AttributeError(key)
    def __setattr__(self,key,value): self[key]=value
    def get(self,key,default=None): return dict.get(self,key,default)
domain=os.environ['DOMAIN'].strip().lower(); path=os.environ['APP_DIR'].rstrip('/')
g=Get()
g.webname=json.dumps({'domain':domain,'domainlist':[]},ensure_ascii=False)
g.path=path; g.port='80'; g.version=os.environ['PHP_SHORT']; g.ps='app-source one-click deploy'
g.ftp='false'; g.sql='false'; g.type_id=0; g.type='PHP'; g.project_type='PHP'; g.codeing='utf8'
g.datauser=''; g.datapassword=''; g.ftp_username=''; g.ftp_password=''; g.set_ssl='0'
try:
    res=panelSite().AddSite(g)
    print(json.dumps(res,ensure_ascii=False))
    if not isinstance(res,dict) or not res.get('siteStatus'):
        raise SystemExit(21)
    sid=res.get('siteId') or public.M('sites').where('name=?',(domain,)).getField('id')
    if not sid:
        raise SystemExit(22)
except Exception:
    traceback.print_exc()
    raise
PY
    cat "$SITE_RESULT"
fi

VERIFY_SITE="$TMP_DIR/site-verify"
DOMAIN="$DOMAIN" APP_DIR="$APP_DIR" PANEL_ROOT="$PANEL_ROOT" "$PANEL_PY" - <<'PY' > "$VERIFY_SITE"
import os,sys
panel=os.environ['PANEL_ROOT']; cls=panel+'/class'
sys.path.insert(0,panel); sys.path.insert(0,cls); os.chdir(panel)
import public
domain=os.environ['DOMAIN']; path=os.environ['APP_DIR'].rstrip('/')
sid=public.M('sites').where('name=?',(domain,)).getField('id')
if not sid: raise SystemExit(31)
spath=public.M('sites').where('id=?',(sid,)).getField('path') or ''
print('%s\t%s' % (sid,spath))
PY
IFS=$'\t' read -r SITE_ID SITE_PATH < "$VERIFY_SITE"
[ "$SITE_PATH" = "$APP_DIR" ] || die "宝塔网站目录不一致: $SITE_PATH"
VHOST="$PANEL_ROOT/vhost/nginx/$DOMAIN.conf"
[ -f "$VHOST" ] || die "宝塔网站已登记，但未生成 Nginx vhost: $VHOST"
[ -d "$APP_DIR" ] || die "宝塔网站已登记，但目标目录不存在: $APP_DIR"
log "宝塔网站登记成功: id=$SITE_ID path=$SITE_PATH"

while IFS= read -r rel; do
    [ -n "$rel" ] || continue
    target="$APP_DIR/${rel#/}"
    chattr -i "$target" 2>/dev/null || true
    rm -rf "$target"
done <<< "$REMOVE_FILES"

if [ "$PROJECT_DIR" = "1" ]; then
    log "检测到本项目现有源码，进入续装模式"
else
    log "写入 GitHub 源码"
    rsync -a --delete "$SRC_DIR/" "$APP_DIR/"
fi

MYSQL_BIN=""
for b in /www/server/mysql/bin/mysql /usr/bin/mysql "$(command -v mysql 2>/dev/null || true)"; do
    [ -n "$b" ] && [ -x "$b" ] && { MYSQL_BIN="$b"; break; }
done
[ -n "$MYSQL_BIN" ] || die "未找到 mysql 客户端"

DB_NAME=""; DB_USER=""; DB_PASS=""; DB_OK=0
if [ -f "$APP_DIR/$DB_CONFIG" ]; then
    DB_STATE="$TMP_DIR/db-state"
    APP_DIR="$APP_DIR" DB_CONFIG="$DB_CONFIG" "$PHP_BIN" -r '
        $c=include rtrim(getenv("APP_DIR"),"/")."/".ltrim(getenv("DB_CONFIG"),"/");
        echo ($c["database"]??"")."\t".($c["username"]??"")."\t".($c["password"]??"");
    ' > "$DB_STATE" 2>/dev/null || true
    IFS=$'\t' read -r DB_NAME DB_USER DB_PASS < "$DB_STATE" || true
    if [ "$DB_NAME" = "BT_DB_NAME" ] || [ "$DB_USER" = "BT_DB_USERNAME" ]; then DB_NAME=""; DB_USER=""; DB_PASS=""; fi
fi

try_db(){
    local host="$1"
    if [ "$host" = socket ]; then
        MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -u"$DB_USER" "$DB_NAME" -Nse 'SELECT 1' >/dev/null 2>&1
    else
        MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -h"$host" -u"$DB_USER" "$DB_NAME" -Nse 'SELECT 1' >/dev/null 2>&1
    fi
}
if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ] && [ -n "$DB_PASS" ]; then
    for host in socket localhost 127.0.0.1; do
        if try_db "$host"; then DB_OK=1; break; fi
    done
fi

if [ "$DB_OK" = "0" ]; then
    TOKEN="$(tr -dc 'a-z0-9' </dev/urandom | head -c 8 || true)"
    [ ${#TOKEN} -eq 8 ] || TOKEN="$(date +%s | tail -c 9)"
    DB_NAME="z${TOKEN}"; DB_USER="$DB_NAME"
    DB_PASS="$(tr -dc 'A-Za-z0-9_@#%+=' </dev/urandom | head -c 24 || true)"
    [ ${#DB_PASS} -ge 16 ] || DB_PASS="Zonoe_${TOKEN}_$(date +%s)"
    log "使用宝塔数据库模块创建 MySQL 数据库"
    DB_RESULT="$TMP_DIR/db-result"
    DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" PANEL_ROOT="$PANEL_ROOT" "$PANEL_PY" - <<'PY' > "$DB_RESULT"
import json,os,sys,traceback
panel=os.environ['PANEL_ROOT']; cls=panel+'/class'
sys.path.insert(0,panel); sys.path.insert(0,cls); os.chdir(panel)
class Get(dict):
    def __getattr__(self,key):
        if key in self: return self[key]
        raise AttributeError(key)
    def __setattr__(self,key,value): self[key]=value
    def get(self,key,default=None): return dict.get(self,key,default)
from database import database
g=Get(); g.name=os.environ['DB_NAME']; g.db_user=os.environ['DB_USER']; g.password=os.environ['DB_PASS']
g.dtype='MySQL'; g.dataAccess='127.0.0.1'; g.address='127.0.0.1'; g.ps='app-source one-click deploy'; g.codeing='utf8mb4'
try:
    res=database().AddDatabase(g)
    print(json.dumps(res,ensure_ascii=False))
    if not isinstance(res,dict) or not res.get('status'):
        raise SystemExit(41)
except Exception:
    traceback.print_exc(); raise
PY
    cat "$DB_RESULT"

    DB_PANEL_STATE="$TMP_DIR/db-panel-state"
    DB_NAME="$DB_NAME" PANEL_ROOT="$PANEL_ROOT" "$PANEL_PY" - <<'PY' > "$DB_PANEL_STATE"
import os,sys
panel=os.environ['PANEL_ROOT']; cls=panel+'/class'
sys.path.insert(0,panel); sys.path.insert(0,cls); os.chdir(panel)
import public
r=public.M('databases').where('name=?',(os.environ['DB_NAME'],)).field('name,username,password').find() or {}
print('%s\t%s\t%s' % (r.get('name',''),r.get('username',''),r.get('password','')))
PY
    IFS=$'\t' read -r PANEL_DB_NAME PANEL_DB_USER PANEL_DB_PASS < "$DB_PANEL_STATE"
    [ -n "$PANEL_DB_NAME" ] && DB_NAME="$PANEL_DB_NAME"
    [ -n "$PANEL_DB_USER" ] && DB_USER="$PANEL_DB_USER"
    [ -n "$PANEL_DB_PASS" ] && DB_PASS="$PANEL_DB_PASS"
fi

DB_OK=0; MYSQL_MODE=""
for host in socket localhost 127.0.0.1; do
    if try_db "$host"; then DB_OK=1; MYSQL_MODE="$host"; break; fi
done
[ "$DB_OK" = "1" ] || die "数据库认证失败：账号=$DB_USER 数据库=$DB_NAME"

log "写入 $DB_CONFIG"
DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" APP_DIR="$APP_DIR" DB_CONFIG="$DB_CONFIG" "$PANEL_PY" - <<'PY'
import os,re
from pathlib import Path
p=Path(os.environ['APP_DIR'])/os.environ['DB_CONFIG'].lstrip('/')
s=p.read_text(encoding='utf-8')
vals={'database':os.environ['DB_NAME'],'username':os.environ['DB_USER'],'password':os.environ['DB_PASS']}
for key,val in vals.items():
    pat=r"('%s'\s*=>\s*Env::get\([^,]+,\s*)'[^']*'" % re.escape(key)
    s,n=re.subn(pat,lambda m:m.group(1)+"'"+val.replace("'","\\'")+"'",s,count=1)
    if n!=1: raise SystemExit('cannot update database key: '+key)
p.write_text(s,encoding='utf-8')
PY

TABLE_EXISTS="0"
case "$MYSQL_MODE" in
    socket) TABLE_EXISTS="$(MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -u"$DB_USER" "$DB_NAME" -Nse "SHOW TABLES LIKE 'fa_admin'" 2>/dev/null | wc -l)" ;;
    localhost) TABLE_EXISTS="$(MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -hlocalhost -u"$DB_USER" "$DB_NAME" -Nse "SHOW TABLES LIKE 'fa_admin'" 2>/dev/null | wc -l)" ;;
    127.0.0.1) TABLE_EXISTS="$(MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -h127.0.0.1 -u"$DB_USER" "$DB_NAME" -Nse "SHOW TABLES LIKE 'fa_admin'" 2>/dev/null | wc -l)" ;;
esac
if [ "$TABLE_EXISTS" = "0" ]; then
    log "导入源码自带 import.sql（MySQL: $MYSQL_MODE）"
    case "$MYSQL_MODE" in
        socket) MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -u"$DB_USER" "$DB_NAME" < "$APP_DIR/import.sql" ;;
        localhost) MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -hlocalhost -u"$DB_USER" "$DB_NAME" < "$APP_DIR/import.sql" ;;
        127.0.0.1) MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -h127.0.0.1 -u"$DB_USER" "$DB_NAME" < "$APP_DIR/import.sql" ;;
    esac
else
    log "检测到 fa_admin 已存在，跳过重复导入 SQL"
fi

RUN_ROOT="$APP_DIR/${RUN_PATH#/}"
[ -d "$RUN_ROOT" ] || die "运行目录不存在: $RUN_ROOT"
REWRITE="$PANEL_ROOT/vhost/rewrite/$DOMAIN.conf"
mkdir -p "$(dirname "$REWRITE")"
cp -a "$VHOST" "$VHOST.deploy-backup-$(date +%Y%m%d%H%M%S)"
APP_DIR="$APP_DIR" RUN_PATH="$RUN_PATH" "$PANEL_PY" - "$VHOST" <<'PY'
import os,re,sys
p=sys.argv[1]; root=os.environ['APP_DIR'].rstrip('/')+'/'+os.environ['RUN_PATH'].strip('/')
s=open(p,encoding='utf-8',errors='ignore').read()
s2,n=re.subn(r'(?m)^(\s*root\s+)[^;]+;',lambda m:m.group(1)+root+';',s,count=1)
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

while IFS= read -r fn; do
    [ -n "$fn" ] || continue
    for ini in "/www/server/php/$PHP_SHORT/etc/php.ini" "/www/server/php/$PHP_SHORT/etc/php-cli.ini"; do
        [ -f "$ini" ] || continue
        FN="$fn" "$PANEL_PY" - "$ini" <<'PY'
import os,re,sys
p=sys.argv[1]; fn=os.environ['FN']
s=open(p,encoding='utf-8',errors='ignore').read(); out=[]
for line in s.splitlines(True):
    if re.match(r'^\s*disable_functions\s*=',line):
        k,v=line.split('=',1)
        fs=[x.strip() for x in v.strip().split(',') if x.strip() and x.strip()!=fn]
        line=k+'= '+','.join(fs)+'\n'
    out.append(line)
open(p,'w',encoding='utf-8').writelines(out)
PY
    done
done <<< "$ENABLE_FUNCTIONS"

chown -R www:www "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
[ -d "$APP_DIR/runtime" ] && chmod -R 775 "$APP_DIR/runtime"

if [ -x "/etc/init.d/php-fpm-$PHP_SHORT" ]; then
    "/etc/init.d/php-fpm-$PHP_SHORT" reload 2>/dev/null || "/etc/init.d/php-fpm-$PHP_SHORT" restart
fi
"$NGINX_BIN" -t || die "Nginx 配置检测失败"
"$NGINX_BIN" -s reload

HTTP_CODE="$(curl -sS -o "$TMP_DIR/http.out" -w '%{http_code}' -H "Host: $DOMAIN" "http://127.0.0.1${SUCCESS_URL}" || true)"
log "成功入口 HTTP 验证: $HTTP_CODE ($SUCCESS_URL)"

printf '\n=================================\n'
printf '软件源一键部署完成\n'
printf 'deploy version: %s\n' "$DEPLOY_VERSION"
printf '宝塔网站ID: %s\n' "$SITE_ID"
printf '域名: http://%s\n' "$DOMAIN"
printf '源码目录: %s\n' "$APP_DIR"
printf '运行目录: %s%s\n' "$APP_DIR" "$RUN_PATH"
printf 'PHP: %s\n' "$PHP_VER"
printf '数据库: %s\n' "$DB_NAME"
printf '数据库用户: %s\n' "$DB_USER"
printf '数据库密码: %s\n' "$DB_PASS"
printf '后台入口: http://%s%s\n' "$DOMAIN" "$SUCCESS_URL"
printf '默认后台账号: %s\n' "$ADMIN_USERNAME"
printf '默认后台密码: %s\n' "$ADMIN_PASSWORD"
printf '=================================\n'
warn "当前脚本不自动申请 SSL；确认 HTTP 正常后可在宝塔申请证书。"
