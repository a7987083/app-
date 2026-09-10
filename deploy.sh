#!/usr/bin/env bash
set -Eeuo pipefail

DEPLOY_VERSION="1.3.0-baota-auto-site"
REPO_URL="https://github.com/a7987083/app-.git"
BRANCH="main"
DEFAULT_DOMAIN="app3.zonoeios.xyz"
PHP_BIN=""
APP_DIR=""
DOMAIN=""
COMPOSER=""
PANEL_PY=""
SITE_ID=""

info(){ printf '[INFO] %s\n' "$*"; }
warn(){ printf '[WARN] %s\n' "$*"; }
die(){ printf '[FAIL] %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "请使用 root 执行"
if [ ! -t 0 ] && [ -r /dev/tty ]; then exec </dev/tty; fi

printf '极速网络 Apple 签名系统 - 一键部署\n'
printf 'deploy version: %s\n' "$DEPLOY_VERSION"
printf '==================================\n'

read -r -p "域名 [${DEFAULT_DOMAIN}]: " DOMAIN || true
DOMAIN="${DOMAIN:-$DEFAULT_DOMAIN}"
DEFAULT_DIR="/www/wwwroot/${DOMAIN}"
read -r -p "站点目录 [${DEFAULT_DIR}]: " APP_DIR || true
APP_DIR="${APP_DIR:-$DEFAULT_DIR}"

[[ "$DOMAIN" =~ ^([A-Za-z0-9-]+\.)+[A-Za-z0-9-]+$ ]] || die "域名格式不正确: $DOMAIN"
[[ "$APP_DIR" == /www/wwwroot/* ]] || die "站点目录必须位于 /www/wwwroot/ 下"
command -v git >/dev/null 2>&1 || die "缺少 git"

# 源码 auto_install.json 指定 PHP 8.2；优先使用宝塔 PHP 8.2。
for p in /www/server/php/82/bin/php /www/server/php/84/bin/php /www/server/php/83/bin/php "$(command -v php 2>/dev/null || true)"; do
    if [ -n "$p" ] && [ -x "$p" ]; then
        if "$p" -r 'exit(version_compare(PHP_VERSION,"8.2.0",">=")?0:1);' >/dev/null 2>&1; then
            PHP_BIN="$p"
            break
        fi
    fi
done
[ -n "$PHP_BIN" ] || die "未找到 PHP >= 8.2"

PHP_VER="$($PHP_BIN -r 'echo PHP_VERSION;' 2>/dev/null || true)"
[ -n "$PHP_VER" ] || die "当前 PHP 无法正常执行: $PHP_BIN"
PHP_PREFIX="$(cd "$(dirname "$PHP_BIN")/.." && pwd)"
PHP_SHORT="$(basename "$PHP_PREFIX")"
PHP_CLI_INI="$($PHP_BIN --ini 2>/dev/null | awk -F': ' '/Loaded Configuration File/{print $2; exit}' || true)"
PHP_FPM_INI="${PHP_PREFIX}/etc/php.ini"
EXT_DIR="$($PHP_BIN -r 'echo ini_get("extension_dir");' 2>/dev/null || true)"
if [ -z "$EXT_DIR" ] || [ ! -d "$EXT_DIR" ]; then
    EXT_DIR="$(find "$PHP_PREFIX" -type d -path '*/lib/php/extensions/*' 2>/dev/null | head -n1 || true)"
fi

info "PHP: ${PHP_BIN} (${PHP_VER})"
info "PHP prefix: ${PHP_PREFIX}"
info "PHP CLI ini: ${PHP_CLI_INI:-未检测到}"
info "PHP FPM ini: ${PHP_FPM_INI}"
info "extension_dir: ${EXT_DIR:-未检测到}"
if [[ "$PHP_VER" != 8.2.* ]]; then
    warn "源码 auto_install.json 指定 PHP 8.2；当前使用 ${PHP_VER}，建议安装宝塔 PHP 8.2。"
fi

# 宝塔 11.5.0 实机源码入口：/www/server/panel/class/panelSite.py
# 使用宝塔自身 panelSite.AddSite 注册网站，而不是手写 SQLite/vhost。
if [ -x /www/server/panel/pyenv/bin/python3 ]; then
    PANEL_PY="/www/server/panel/pyenv/bin/python3"
elif [ -x /www/server/panel/pyenv/bin/python ]; then
    PANEL_PY="/www/server/panel/pyenv/bin/python"
else
    die "未找到宝塔 Panel Python: /www/server/panel/pyenv/bin/python3"
fi
[ -f /www/server/panel/class/panelSite.py ] || die "未找到宝塔 panelSite.py"

baota_ensure_site() {
    local out
    info "检查宝塔网站记录: ${DOMAIN}"
    out="$(DOMAIN="$DOMAIN" APP_DIR="$APP_DIR" PANEL_PHP_SHORT="$PHP_SHORT" "$PANEL_PY" - <<'PY'
import json, os, sys, traceback
PANEL='/www/server/panel'
CLASS=PANEL+'/class'
sys.path.insert(0, PANEL)
sys.path.insert(0, CLASS)
os.chdir(PANEL)
import public
from panelSite import panelSite

class Get(dict):
    def __getattr__(self, key):
        if key in self: return self[key]
        raise AttributeError(key)
    def __setattr__(self, key, value):
        self[key] = value
    def get(self, key, default=None):
        return dict.get(self, key, default)

domain=os.environ['DOMAIN'].strip().lower()
path=os.environ['APP_DIR'].rstrip('/')
phpver=os.environ.get('PANEL_PHP_SHORT','82')

try:
    site_id = public.M('sites').where('name=?', (domain,)).getField('id')
    if site_id:
        site_path = public.M('sites').where('id=?', (site_id,)).getField('path')
        print('BT_SITE_EXISTING=1')
        print('BT_SITE_ID={}'.format(site_id))
        print('BT_SITE_PATH={}'.format(site_path or ''))
        sys.exit(0)

    g=Get()
    g.webname=json.dumps({'domain': domain, 'domainlist': []}, ensure_ascii=False)
    g.path=path
    g.port='80'
    g.version=phpver
    g.ps='zonoe deploy'
    g.ftp='false'
    g.sql='false'
    g.type_id=0
    g.project_type='PHP'
    g.deploy_type='git'
    g.codeing='utf8mb4'
    g.datauser=''
    g.datapassword=''
    g.ftp_username=''
    g.ftp_password=''
    g.set_ssl='0'

    res=panelSite().AddSite(g)
    if not isinstance(res, dict) or not res.get('status'):
        print('BT_ERROR={}'.format(json.dumps(res, ensure_ascii=False)))
        sys.exit(21)

    site_id=res.get('siteId') or public.M('sites').where('name=?', (domain,)).getField('id')
    if not site_id:
        print('BT_ERROR=AddSite returned success but site id was not found')
        sys.exit(22)
    print('BT_SITE_CREATED=1')
    print('BT_SITE_ID={}'.format(site_id))
    print('BT_SITE_PATH={}'.format(path))
except Exception:
    traceback.print_exc()
    sys.exit(23)
PY
)" || {
        printf '%s\n' "$out" >&2
        die "宝塔创建/登记站点失败"
    }
    printf '%s\n' "$out"
    SITE_ID="$(printf '%s\n' "$out" | awk -F= '/^BT_SITE_ID=/{print $2; exit}')"
    [ -n "$SITE_ID" ] || die "宝塔返回成功，但未取得 siteId"
    local bt_path
    bt_path="$(printf '%s\n' "$out" | sed -n 's/^BT_SITE_PATH=//p' | head -n1)"
    if [ -n "$bt_path" ] && [ "$bt_path" != "$APP_DIR" ]; then
        die "宝塔中域名 ${DOMAIN} 已绑定到其他目录: ${bt_path}；当前要求: ${APP_DIR}"
    fi
    info "宝塔站点 ID: ${SITE_ID}"
}

baota_ensure_site

NGINX_CONF="/www/server/panel/vhost/nginx/${DOMAIN}.conf"
REWRITE_CONF="/www/server/panel/vhost/rewrite/${DOMAIN}.conf"
[ -f "$NGINX_CONF" ] || die "宝塔已登记站点，但未生成 Nginx vhost: ${NGINX_CONF}"

# 已有项目保留配置；不存在时从 GitHub 拉取。
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
    local ext="$1"
    if [ "$ext" = "opcache" ]; then
        "$PHP_BIN" -r 'exit(function_exists("opcache_get_status") ? 0 : 1);' >/dev/null 2>&1
    else
        "$PHP_BIN" -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$ext" >/dev/null 2>&1
    fi
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

append_opcache_ini() {
    local ini="$1"
    local so="$2"
    [ -f "$ini" ] || return 0
    if ! grep -Eqi '^[[:space:]]*zend_extension[[:space:]]*=.*opcache(\.so)?([[:space:]]|$)' "$ini"; then
        {
            printf '\n; zonoe deploy: enable Zend OPcache\n'
            printf 'zend_extension=%s\n' "$so"
        } >> "$ini"
    fi
    if [ "$ini" = "$PHP_CLI_INI" ] && ! grep -Eqi '^[[:space:]]*opcache\.enable_cli[[:space:]]*=[[:space:]]*1' "$ini"; then
        printf 'opcache.enable_cli=1\n' >> "$ini"
    fi
}

enable_existing_extension() {
    local ext="$1"
    local so=""
    [ -n "$EXT_DIR" ] || return 1
    so="${EXT_DIR}/${ext}.so"
    [ -f "$so" ] || return 1
    if [ "$ext" = "opcache" ]; then
        if ! "$PHP_BIN" -n -d "zend_extension=${so}" -d opcache.enable_cli=1 -r 'exit(function_exists("opcache_get_status") ? 0 : 1);' >/dev/null 2>&1; then
            warn "已找到 ${so}，但当前 PHP 无法直接加载该 OPcache 模块"
            return 1
        fi
        append_opcache_ini "$PHP_CLI_INI" "$so"
        append_opcache_ini "$PHP_FPM_INI" "$so"
    else
        [ -n "$PHP_CLI_INI" ] && [ -f "$PHP_CLI_INI" ] || return 1
        if ! grep -Eqi "^[[:space:]]*extension[[:space:]]*=.*${ext}(\\.so)?([[:space:]]|$)" "$PHP_CLI_INI"; then
            {
                printf '\n; zonoe deploy: enable %s\n' "$ext"
                printf 'extension=%s\n' "$so"
            } >> "$PHP_CLI_INI"
        fi
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
        php_ext_loaded "$ext" && return 0
    fi
    return 1
}

compile_opcache_exact_version() {
    local phpize="${PHP_PREFIX}/bin/phpize"
    local phpconfig="${PHP_PREFIX}/bin/php-config"
    local tmp="/tmp/zonoe-opcache-${PHP_VER}-$$"
    local tarball="php-${PHP_VER}.tar.gz"
    local srcurl="https://www.php.net/distributions/${tarball}"
    [ -x "$phpize" ] || { warn "缺少 ${phpize}，无法编译 OPcache"; return 1; }
    [ -x "$phpconfig" ] || { warn "缺少 ${phpconfig}，无法编译 OPcache"; return 1; }
    command -v make >/dev/null 2>&1 || { warn "缺少 make，无法编译 OPcache"; return 1; }
    command -v curl >/dev/null 2>&1 || { warn "缺少 curl，无法下载 PHP 源码"; return 1; }
    command -v tar >/dev/null 2>&1 || { warn "缺少 tar，无法解压 PHP 源码"; return 1; }
    info "使用 PHP ${PHP_VER} 同版本源码编译 ext/opcache"
    rm -rf "$tmp"
    mkdir -p "$tmp"
    (
        cd "$tmp"
        curl -fL --retry 2 --connect-timeout 15 "$srcurl" -o "$tarball"
        tar -xzf "$tarball"
        cd "php-${PHP_VER}/ext/opcache"
        "$phpize"
        ./configure --with-php-config="$phpconfig" --enable-opcache
        make -j"$(getconf _NPROCESSORS_ONLN 2>/dev/null || echo 1)"
        make install
    ) || { rm -rf "$tmp"; return 1; }
    rm -rf "$tmp"
    EXT_DIR="$($phpconfig --extension-dir 2>/dev/null || true)"
    if [ -z "$EXT_DIR" ] || [ ! -d "$EXT_DIR" ]; then
        EXT_DIR="$($PHP_BIN -r 'echo ini_get("extension_dir");' 2>/dev/null || true)"
    fi
    if [ -z "$EXT_DIR" ] || [ ! -d "$EXT_DIR" ]; then
        EXT_DIR="$(find "$PHP_PREFIX" -type d -path '*/lib/php/extensions/*' 2>/dev/null | head -n1 || true)"
    fi
    [ -n "$EXT_DIR" ] && [ -f "${EXT_DIR}/opcache.so" ] || {
        warn "编译完成，但未找到安装后的 opcache.so；extension_dir=${EXT_DIR:-空}"
        return 1
    }
    info "OPcache 已安装到: ${EXT_DIR}/opcache.so"
    enable_existing_extension opcache
}

ensure_auto_extension() {
    local ext="$1"
    if php_ext_loaded "$ext"; then
        info "PHP 扩展 ${ext}: OK"
        return 0
    fi
    warn "PHP 扩展 ${ext} 缺失，开始自动处理"
    if enable_existing_extension "$ext"; then
        info "PHP 扩展 ${ext}: 已启用现有模块"
        return 0
    fi
    if [ "$ext" = "opcache" ]; then
        if compile_opcache_exact_version; then
            info "PHP 扩展 opcache: 同版本源码编译并启用成功"
            return 0
        fi
        die "PHP 扩展 opcache 自动处理失败。请检查上方 OPcache 加载验证输出。"
    fi
    if bt_install_extension "$ext"; then
        info "PHP 扩展 ${ext}: 自动安装成功"
        return 0
    fi
    die "PHP 扩展 ${ext} 自动安装失败。未改用系统 PHP 包，避免装到错误 PHP 版本。"
}

for ext in opcache fileinfo redis; do ensure_auto_extension "$ext"; done

MISSING_CORE=""
for ext in pdo openssl zip dom; do php_ext_loaded "$ext" || MISSING_CORE="${MISSING_CORE} ${ext}"; done
[ -z "$MISSING_CORE" ] || die "PHP 缺少 composer.json 要求的基础扩展:${MISSING_CORE}。这些不在源码 auto_install.json 的自动安装列表中。"

DISABLED_RAW="$($PHP_BIN -r 'echo preg_replace("/\\s+/","",ini_get("disable_functions"));' 2>/dev/null || true)"
DISABLED=",${DISABLED_RAW},"
for fn in proc_open pcntl_signal pcntl_alarm symlink; do
    case "$DISABLED" in *",${fn},"*) die "PHP 禁用了源码 auto_install.json 要求的函数: ${fn}" ;; esac
done
info "PHP 扩展/函数检查通过"

if command -v composer >/dev/null 2>&1; then
    COMPOSER="$(command -v composer)"
elif [ -x /usr/local/bin/composer ]; then
    COMPOSER="/usr/local/bin/composer"
else
    die "未找到 composer"
fi

COMPOSER_DISABLED="$(printf '%s' "$DISABLED_RAW" | tr ',' '\n' | sed '/^[[:space:]]*$/d' | grep -vx 'putenv' | paste -sd, - || true)"
info "Composer: ${COMPOSER}（使用 ${PHP_BIN} 执行）"
if [[ ",${DISABLED_RAW}," == *",putenv,"* ]]; then
    info "检测到 putenv 被禁用：仅在 Composer 当前进程临时允许，不修改全局 php.ini"
fi
COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" -d "disable_functions=${COMPOSER_DISABLED}" "$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

mkdir -p storage/AppleSignV2 storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod 755 storage/AppleSignV2
chown -R www:www storage bootstrap/cache 2>/dev/null || true
find storage bootstrap/cache -type d -exec chmod 775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +
# 原生安装器需要写 config/api.php
chown www:www config/api.php 2>/dev/null || true
chmod 664 config/api.php 2>/dev/null || true
rm -f index.html 404.html .user.ini

"$PHP_BIN" artisan optimize:clear || die "Laravel 缓存清理失败"

# 用宝塔自身 API 确保 PHP 版本=82、运行目录=/public、Laravel 伪静态。
baota_configure_site() {
    # SetSiteRunPath 会移动旧运行目录的 .user.ini；git 模式建站没有该文件，临时创建以保持宝塔自身流程干净。
    local temp_user_ini=0
    if [ ! -e "$APP_DIR/.user.ini" ] && [ ! -e "$APP_DIR/public/.user.ini" ]; then
        : > "$APP_DIR/.user.ini"
        chown www:www "$APP_DIR/.user.ini" 2>/dev/null || true
        temp_user_ini=1
    fi

    DOMAIN="$DOMAIN" APP_DIR="$APP_DIR" SITE_ID="$SITE_ID" PANEL_PHP_SHORT="$PHP_SHORT" "$PANEL_PY" - <<'PY'
import json, os, sys, traceback
PANEL='/www/server/panel'
CLASS=PANEL+'/class'
sys.path.insert(0, PANEL)
sys.path.insert(0, CLASS)
os.chdir(PANEL)
import public
from panelSite import panelSite

class Get(dict):
    def __getattr__(self, key):
        if key in self: return self[key]
        raise AttributeError(key)
    def __setattr__(self, key, value): self[key]=value
    def get(self, key, default=None): return dict.get(self, key, default)

domain=os.environ['DOMAIN']
site_id=str(os.environ['SITE_ID'])
phpver=os.environ.get('PANEL_PHP_SHORT','82')
rewrite_file='/www/server/panel/vhost/rewrite/{}.conf'.format(domain)
rewrite_data='''location / {
    if (!-e $request_filename){
        rewrite ^(.*)$ /index.php?s=$1 last;
        break;
    }
}
'''

try:
    ps=panelSite()

    g=Get(siteName=domain, version=phpver, id=site_id)
    res=ps.SetPHPVersion(g)
    if isinstance(res, dict) and not res.get('status'):
        print('SetPHPVersion failed: {}'.format(json.dumps(res, ensure_ascii=False)))
        sys.exit(31)

    g=Get(id=site_id, siteName=domain, runPath='/public')
    res=ps.SetSiteRunPath(g)
    if isinstance(res, dict) and not res.get('status'):
        print('SetSiteRunPath failed: {}'.format(json.dumps(res, ensure_ascii=False)))
        sys.exit(32)

    os.makedirs(os.path.dirname(rewrite_file), exist_ok=True)
    if not os.path.exists(rewrite_file):
        open(rewrite_file, 'a').close()
    sites=json.dumps([{'id': int(site_id), 'name': domain, 'file': rewrite_file}], ensure_ascii=False)
    g=Get(sites=sites, rewrite_data=rewrite_data)
    res=ps.SetRewriteLists(g)
    # 该接口返回批量结果结构；最终再用 checkWebConfig 与文件内容做硬验证。
    if not os.path.exists(rewrite_file):
        print('rewrite file missing after SetRewriteLists')
        sys.exit(33)

    err=public.checkWebConfig()
    if err is not True:
        print('Nginx config invalid: {}'.format(err))
        sys.exit(34)

    run=ps.GetRunPath(Get(id=site_id, siteName=domain))
    if run != '/public':
        print('runPath verify failed: {!r}'.format(run))
        sys.exit(35)

    print('BT_CONFIG_OK=1')
    print('BT_RUN_PATH={}'.format(run))
except Exception:
    traceback.print_exc()
    sys.exit(36)
PY
    local rc=$?
    if [ "$temp_user_ini" -eq 1 ]; then
        rm -f "$APP_DIR/.user.ini" "$APP_DIR/public/.user.ini" 2>/dev/null || true
    fi
    [ "$rc" -eq 0 ] || die "宝塔站点配置失败"
}

baota_configure_site

# 宝塔 API 完成后做文件级硬校验。
[ -f "$NGINX_CONF" ] || die "Nginx vhost 不存在: $NGINX_CONF"
grep -Eq "server_name[[:space:]]+([^;[:space:]]+[[:space:]]+)*${DOMAIN//./\\.}([[:space:]]+[^;]+)*;" "$NGINX_CONF" || die "vhost 未绑定当前域名: $DOMAIN"
grep -Fq "root ${APP_DIR}/public;" "$NGINX_CONF" || die "宝塔运行目录未正确设置为 ${APP_DIR}/public"
grep -Fq "enable-php-${PHP_SHORT}.conf" "$NGINX_CONF" || die "宝塔站点未绑定 PHP ${PHP_SHORT}"
[ -f "$REWRITE_CONF" ] || die "伪静态文件不存在: $REWRITE_CONF"

if [ -x /www/server/nginx/sbin/nginx ]; then
    /www/server/nginx/sbin/nginx -t || die "Nginx 配置检查失败"
    /www/server/nginx/sbin/nginx -s reload || die "Nginx reload 失败"
elif command -v nginx >/dev/null 2>&1; then
    nginx -t || die "Nginx 配置检查失败"
    nginx -s reload || die "Nginx reload 失败"
else
    die "未找到 Nginx 可执行文件"
fi

info "宝塔网站已登记: ${DOMAIN} (siteId=${SITE_ID})"
info "运行目录: ${APP_DIR}/public"
info "PHP: ${PHP_SHORT}"
info "伪静态: ${REWRITE_CONF}"

# HTTP 本机命中验证：只验证该 Host 已由当前 vhost 接管，不把业务页面状态码作为部署失败依据。
HTTP_CODE="$(curl -sS -o /tmp/zonoe-deploy-http-body.$$ -w '%{http_code}' -H "Host: ${DOMAIN}" http://127.0.0.1/install 2>/dev/null || true)"
if [ -n "$HTTP_CODE" ]; then
    info "本机 HTTP /install 状态码: ${HTTP_CODE}"
    if grep -Fq 'url=./appstore' /tmp/zonoe-deploy-http-body.$$ 2>/dev/null; then
        rm -f /tmp/zonoe-deploy-http-body.$$
        die "HTTP 仍命中其他 appstore 站点，vhost 路由未生效"
    fi
fi
rm -f /tmp/zonoe-deploy-http-body.$$ 2>/dev/null || true

printf '\n==================================\n'
printf '基础部署完成（源码原生安装流程）\n'
printf 'deploy version: %s\n' "$DEPLOY_VERSION"
printf '宝塔站点: 已创建/已登记\n'
printf '域名: %s\n' "$DOMAIN"
printf '源码目录: %s\n' "$APP_DIR"
printf '运行目录: %s/public\n' "$APP_DIR"
printf 'PHP: %s\n' "$PHP_VER"
printf 'PHP扩展: opcache/fileinfo/redis 已验证\n'
printf '数据库配置: 未写入（按源码设计由 /install 页面填写）\n'
printf '下一步访问: http://%s/install\n' "$DOMAIN"
printf '==================================\n'
