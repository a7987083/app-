#!/usr/bin/env bash
set -Eeuo pipefail

DEPLOY_VERSION="2.0.0-baota-native-site"
REPO_URL="${REPO_URL:-https://github.com/a7987083/app-.git}"
REPO_BRANCH="${REPO_BRANCH:-main}"
SOURCE_TARBALL="${SOURCE_TARBALL:-}"
PANEL_ROOT="${PANEL_ROOT:-/www/server/panel}"
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

TMP_DIR="$(mktemp -d /tmp/app-source-deploy.XXXXXX)"
SRC_DIR="$TMP_DIR/src"
mkdir -p "$SRC_DIR"

log "部署版本: $DEPLOY_VERSION"
log "目标域名: $DOMAIN"
log "目标目录: $APP_DIR"
log "选择 PHP: $PHP_VER ($PHP_SHORT)"

# 先准备完整源码，任何站点/数据库操作前先验证包完整性。
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

# 识别目录状态：本项目半成品允许续装；宝塔默认文件允许覆盖；陌生业务目录禁止覆盖。
PROJECT_DIR=0
if [ -f "$APP_DIR/application/database.php" ] \
   && [ -f "$APP_DIR/import.sql" ] \
   && [ -f "$APP_DIR/public/FRKToHDckx.php" ] \
   && [ -f "$APP_DIR/vendor/autoload.php" ]; then
    PROJECT_DIR=1
fi

UNKNOWN_ENTRY=""
if [ -d "$APP_DIR" ] && [ "$PROJECT_DIR" = "0" ]; then
    UNKNOWN_ENTRY="$(find "$APP_DIR" -mindepth 1 -maxdepth 1 \
        ! -name '.user.ini' ! -name 'index.html' ! -name '404.html' \
        -print -quit 2>/dev/null || true)"
fi
[ -z "$UNKNOWN_ENTRY" ] || die "目标目录已有非本项目文件: $UNKNOWN_ENTRY；停止以避免覆盖"

# 查询宝塔真实网站记录。name/path 任一命中都视为已登记。
PANEL_STATE="$($PANEL_PY - <<PY
import sys
sys.path.insert(0, '$PANEL_ROOT/class')
sys.path.insert(0, '$PANEL_ROOT')
import public
site = public.M('sites').where('name=? OR path=?', ('$DOMAIN', '$APP_DIR')).field('id,name,path').find()
if not site:
    print('0\t\t\t')
else:
    print('1\t%s\t%s' % (site.get('id',''), site.get('name','')))
PY
)" || die "无法读取宝塔网站数据"
IFS=$'\t' read -r SITE_EXISTS SITE_ID SITE_NAME <<< "$PANEL_STATE"

# 网站必须由宝塔原生普通 PHP 建站流程登记。不要传 deploy_type=git，也不要把数据库创建绑在 AddSite 上。
if [ "$SITE_EXISTS" != "1" ]; then
    if [ "$PROJECT_DIR" = "1" ]; then
        warn "检测到本项目半成品目录，但宝塔网站列表无记录；现在补登记为普通 PHP 网站"
    else
        log "使用宝塔原生 AddSite 创建普通 PHP 网站"
    fi

    SITE_RESULT="$DOMAIN=$DOMAIN APP_DIR=$APP_DIR PHP_SHORT=$PHP_SHORT PANEL_ROOT=$PANEL_ROOT "$PANEL_PY" - <<'PY'
import os,sys,json
panel_root=os.environ.get('PANEL_ROOT','/www/server/panel')
sys.path.insert(0, panel_root + '/class')
sys.path.insert(0, panel_root)
from panelSite import panelSite

class G(dict):
    __getattr__ = dict.get
    __setattr__ = dict.__setitem__

g=G()
domain=os.environ['DOMAIN']
g.webname=json.dumps({'domain':domain,'domainlist':[]})
g.path=os.environ['APP_DIR']
g.port='80'
g.version=os.environ['PHP_SHORT']
g.ps='app-source one-click deploy'
g.ftp='false'
g.sql='false'
g.type_id=0
g.type='PHP'
g.project_type='PHP'
g.codeing='utf8'
g.datauser=''
g.datapassword=''
g.ftp_username=''
g.ftp_password=''
g.set_ssl='0'
# 关键：不设置 deploy_type=git。让宝塔走普通 PHP 网站登记流程。
res=panelSite().AddSite(g)
print(json.dumps(res,ensure_ascii=False))
if not isinstance(res,dict) or not res.get('siteStatus'):
    raise SystemExit(21)
PY
    )" || die "宝塔普通 PHP 网站创建失败: ${SITE_RESULT:-无返回}"
    printf '%s\n' "$SITE_RESULT" | tail -1
fi

# 硬验证：网站必须真实进入宝塔 sites 表，否则立即停止，不再生成“面板不可见”的半成品。
VERIFY_SITE="$($PANEL_PY - <<PY
import sys
sys.path.insert(0, '$PANEL_ROOT/class')
sys.path.insert(0, '$PANEL_ROOT')
import public
site = public.M('sites').where('name=? OR path=?', ('$DOMAIN', '$APP_DIR')).field('id,name,path').find()
if not site:
    raise SystemExit(31)
print('%s\t%s\t%s' % (site.get('id',''), site.get('name',''), site.get('path','')))
PY
)" || die "宝塔 AddSite 返回后仍无法在网站列表数据中找到该站点，停止部署"
IFS=$'\t' read -r SITE_ID SITE_NAME SITE_PATH <<< "$VERIFY_SITE"
log "宝塔网站登记成功: id=$SITE_ID name=$SITE_NAME path=$SITE_PATH"

# 宝塔普通建站可能生成默认文件；它们不是业务文件，可安全清理。
mkdir -p "$APP_DIR"
for f in .user.ini index.html 404.html; do
    chattr -i "$APP_DIR/$f" 2>/dev/null || true
    rm -f "$APP_DIR/$f"
done

if [ "$PROJECT_DIR" = "1" ]; then
    log "检测到本项目现有源码，进入续装模式，不因目录非空退出"
else
    log "写入 GitHub 源码"
    rsync -a --delete "$SRC_DIR/" "$APP_DIR/"
fi

# 尝试复用上次已经写好的数据库配置；能真实认证才算可复用。
DB_NAME=""
DB_USER=""
DB_PASS=""
if [ -f "$APP_DIR/application/database.php" ]; then
    DB_STATE="$APP_DIR=$APP_DIR "$PHP_BIN" -r '
        $c=include getenv("APP_DIR")."/application/database.php";
        echo ($c["database"]??"")."\t".($c["username"]??"")."\t".($c["password"]??"");
    ' 2>/dev/null || true)"
    IFS=$'\t' read -r DB_NAME DB_USER DB_PASS <<< "$DB_STATE"
    if [ "$DB_NAME" = "BT_DB_NAME" ] || [ "$DB_USER" = "BT_DB_USERNAME" ]; then
        DB_NAME=""; DB_USER=""; DB_PASS=""
    fi
fi

MYSQL_BIN=""
for b in /www/server/mysql/bin/mysql /usr/bin/mysql "$(command -v mysql 2>/dev/null || true)"; do
    [ -n "$b" ] && [ -x "$b" ] && { MYSQL_BIN="$b"; break; }
done
[ -n "$MYSQL_BIN" ] || die "未找到 mysql 客户端"

DB_OK=0
if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ] && [ -n "$DB_PASS" ]; then
    for host in socket localhost 127.0.0.1; do
        if [ "$host" = socket ]; then
            MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -u"$DB_USER" "$DB_NAME" -Nse 'SELECT 1' >/dev/null 2>&1 && DB_OK=1 && break
        else
            MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -h"$host" -u"$DB_USER" "$DB_NAME" -Nse 'SELECT 1' >/dev/null 2>&1 && DB_OK=1 && break
        fi
    done
fi

# 没有可用数据库时，使用宝塔数据库模块单独创建；网站登记不受数据库结果影响。
if [ "$DB_OK" = "0" ]; then
    TOKEN="$(tr -dc 'a-z0-9' </dev/urandom | head -c 8 || true)"
    [ ${#TOKEN} -eq 8 ] || TOKEN="$(date +%s | tail -c 9)"
    DB_USER="z${TOKEN}"
    DB_NAME="$DB_USER"
    DB_PASS="$(tr -dc 'A-Za-z0-9_@#%+=' </dev/urandom | head -c 24 || true)"
    [ ${#DB_PASS} -ge 16 ] || DB_PASS="Zonoe_${TOKEN}_$(date +%s)"

    log "使用宝塔数据库模块创建 MySQL 数据库"
    DB_RESULT="$DB_NAME=$DB_NAME DB_USER=$DB_USER DB_PASS=$DB_PASS PANEL_ROOT=$PANEL_ROOT "$PANEL_PY" - <<'PY'
import os,sys,json
panel_root=os.environ.get('PANEL_ROOT','/www/server/panel')
sys.path.insert(0, panel_root + '/class')
sys.path.insert(0, panel_root)
import public

class G(dict):
    __getattr__ = dict.get
    __setattr__ = dict.__setitem__

g=G()
g.name=os.environ['DB_NAME']
g.db_user=os.environ['DB_USER']
g.password=os.environ['DB_PASS']
g.dtype='MySQL'
g.dataAccess='127.0.0.1'
g.address='127.0.0.1'
g.ps='app-source one-click deploy'
g.codeing='utf8mb4'

try:
    from database import database
    res=database().AddDatabase(g)
except Exception as e:
    print(json.dumps({'status':False,'msg':str(e)},ensure_ascii=False))
    raise
print(json.dumps(res,ensure_ascii=False))
if not isinstance(res,dict) or not res.get('status'):
    raise SystemExit(41)
PY
    )" || die "宝塔数据库创建失败: ${DB_RESULT:-无返回}"
    printf '%s\n' "$DB_RESULT" | tail -1

    # 用宝塔面板记录作为最终凭证来源。
    DB_PANEL_STATE="$DB_NAME=$DB_NAME PANEL_ROOT=$PANEL_ROOT "$PANEL_PY" - <<'PY'
import os,sys
panel_root=os.environ.get('PANEL_ROOT','/www/server/panel')
sys.path.insert(0, panel_root + '/class')
sys.path.insert(0, panel_root)
import public
name=os.environ['DB_NAME']
r=public.M('databases').where('name=?',(name,)).field('name,username,password').find() or {}
print('%s\t%s\t%s' % (r.get('name',''),r.get('username',''),r.get('password','')))
PY
    )"
    IFS=$'\t' read -r PANEL_DB_NAME PANEL_DB_USER PANEL_DB_PASS <<< "$DB_PANEL_STATE"
    [ -n "$PANEL_DB_NAME" ] && DB_NAME="$PANEL_DB_NAME"
    [ -n "$PANEL_DB_USER" ] && DB_USER="$PANEL_DB_USER"
    [ -n "$PANEL_DB_PASS" ] && DB_PASS="$PANEL_DB_PASS"
fi

# 写入/刷新数据库配置。兼容源码占位符和之前失败部署留下的旧值。
log "写入 application/database.php"
DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" APP_DIR="$APP_DIR" "$PANEL_PY" - <<'PY'
import os,re
from pathlib import Path
p=Path(os.environ['APP_DIR'])/'application/database.php'
s=p.read_text(encoding='utf-8')
vals={
    'database':os.environ['DB_NAME'],
    'username':os.environ['DB_USER'],
    'password':os.environ['DB_PASS'],
}
for key,val in vals.items():
    pat=r"('%s'\s*=>\s*Env::get\([^,]+,\s*)'[^']*'" % re.escape(key)
    s,n=re.subn(pat,lambda m:m.group(1)+"'"+val.replace("'","\\'")+"'",s,count=1)
    if n!=1:
        raise SystemExit('cannot update database key: '+key)
p.write_text(s,encoding='utf-8')
PY

# 再次验证数据库认证，并选择实际可用连接方式。
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

# 已导入过就不重复破坏；缺少核心表才导入完整 SQL。
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

log "设置运行目录 /public 与伪静态"
VHOST="$PANEL_ROOT/vhost/nginx/$DOMAIN.conf"
REWRITE="$PANEL_ROOT/vhost/rewrite/$DOMAIN.conf"
[ -f "$VHOST" ] || die "宝塔网站已登记，但未找到 Nginx vhost: $VHOST"
mkdir -p "$(dirname "$REWRITE")"

cp -a "$VHOST" "$VHOST.deploy-backup-$(date +%Y%m%d%H%M%S)"
APP_DIR="$APP_DIR" "$PANEL_PY" - "$VHOST" <<'PY'
import os,re,sys
p=sys.argv[1]
root=os.environ['APP_DIR'].rstrip('/')+'/public'
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

# 当前项目需要 putenv；仅从禁用列表移除，不改其他 PHP 配置。
enable_putenv(){
    local ini="$1"
    [ -f "$ini" ] || return 0
    "$PANEL_PY" - "$ini" <<'PY'
import re,sys
p=sys.argv[1]
s=open(p,encoding='utf-8',errors='ignore').read(); out=[]
for line in s.splitlines(True):
    if re.match(r'^\s*disable_functions\s*=',line):
        k,v=line.split('=',1)
        fs=[x.strip() for x in v.strip().split(',') if x.strip() and x.strip()!='putenv']
        line=k+'= '+','.join(fs)+'\n'
    out.append(line)
open(p,'w',encoding='utf-8').writelines(out)
PY
}
enable_putenv "/www/server/php/$PHP_SHORT/etc/php.ini"
enable_putenv "/www/server/php/$PHP_SHORT/etc/php-cli.ini"

chown -R www:www "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
[ -d "$APP_DIR/runtime" ] && chmod -R 775 "$APP_DIR/runtime"
chmod 755 "$APP_DIR/public/index.php" "$APP_DIR/public/FRKToHDckx.php"

if [ -x "/etc/init.d/php-fpm-$PHP_SHORT" ]; then
    "/etc/init.d/php-fpm-$PHP_SHORT" reload 2>/dev/null || "/etc/init.d/php-fpm-$PHP_SHORT" restart
else
    warn "未找到 /etc/init.d/php-fpm-$PHP_SHORT，请在宝塔面板中重载 PHP $PHP_VER"
fi

"$NGINX_BIN" -t || die "Nginx 配置检测失败；vhost 备份已保留"
"$NGINX_BIN" -s reload

HTTP_CODE="$(curl -sS -o "$TMP_DIR/http.out" -w '%{http_code}' -H "Host: $DOMAIN" http://127.0.0.1/ || true)"
log "本机 HTTP 验证: $HTTP_CODE"

printf '\n=================================\n'
printf '软件源一键部署完成\n'
printf 'deploy version: %s\n' "$DEPLOY_VERSION"
printf '宝塔网站ID: %s\n' "$SITE_ID"
printf '宝塔网站名: %s\n' "$SITE_NAME"
printf '域名: http://%s\n' "$DOMAIN"
printf '源码目录: %s\n' "$APP_DIR"
printf '运行目录: %s/public\n' "$APP_DIR"
printf 'PHP: %s\n' "$PHP_VER"
printf '数据库: %s\n' "$DB_NAME"
printf '数据库用户: %s\n' "$DB_USER"
printf '数据库密码: %s\n' "$DB_PASS"
printf '后台入口: http://%s/FRKToHDckx.php\n' "$DOMAIN"
printf '=================================\n'
warn "当前脚本不自动申请 SSL；确认 HTTP 正常后可在宝塔申请证书。"
