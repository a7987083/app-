#!/usr/bin/env bash
set -euo pipefail

if [ "${EUID:-$(id -u)}" -ne 0 ]; then
  echo "ERROR: run as root: sudo bash scripts/install-ipa-parser-service.sh" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PYTHON="${ZONOE_PYTHON3:-$(command -v python3 || true)}"
HOST="${ZONOE_IPA_PARSER_HOST:-127.0.0.1}"
PORT="${ZONOE_IPA_PARSER_PORT:-19191}"
SERVICE_NAME="${ZONOE_IPA_PARSER_SERVICE:-zonoe-ipa-parser.service}"
UNIT="/etc/systemd/system/${SERVICE_NAME}"

if [ -z "$PYTHON" ] || [ ! -x "$PYTHON" ]; then
  echo "ERROR: python3 not found" >&2
  exit 1
fi
if [ ! -f "$ROOT/scripts/ipa-parser-service.py" ] || [ ! -f "$ROOT/scripts/ipa-range-info.py" ]; then
  echo "ERROR: parser service files missing under $ROOT/scripts" >&2
  exit 1
fi
if ! command -v systemctl >/dev/null 2>&1; then
  echo "ERROR: systemctl not found; use your process supervisor to run ipa-parser-service.py persistently" >&2
  exit 1
fi

if [ -n "${ZONOE_IPA_PARSER_USER:-}" ]; then
  SERVICE_USER="$ZONOE_IPA_PARSER_USER"
else
  OWNER="$(stat -c '%U' "$ROOT" 2>/dev/null || true)"
  if [ -n "$OWNER" ] && [ "$OWNER" != "root" ] && id "$OWNER" >/dev/null 2>&1; then
    SERVICE_USER="$OWNER"
  elif id www >/dev/null 2>&1; then
    SERVICE_USER="www"
  elif id www-data >/dev/null 2>&1; then
    SERVICE_USER="www-data"
  else
    echo "ERROR: cannot determine non-root service user; set ZONOE_IPA_PARSER_USER" >&2
    exit 1
  fi
fi

case "$HOST" in
  127.0.0.1|localhost|::1) ;;
  *) echo "ERROR: parser service must bind loopback only" >&2; exit 1 ;;
esac
case "$PORT" in
  ''|*[!0-9]*) echo "ERROR: invalid port: $PORT" >&2; exit 1 ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
  echo "ERROR: invalid port: $PORT" >&2
  exit 1
fi

"$PYTHON" "$ROOT/scripts/ipa-range-info.py" --self-test
"$PYTHON" "$ROOT/scripts/ipa-parser-service.py" --health-self-test

cat > "$UNIT" <<EOF
[Unit]
Description=ZONOE Persistent IPA Parser Service
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=${SERVICE_USER}
WorkingDirectory=${ROOT}
ExecStart=${PYTHON} ${ROOT}/scripts/ipa-parser-service.py --host ${HOST} --port ${PORT}
Restart=always
RestartSec=2
TimeoutStopSec=10
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ProtectHome=false

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now "$SERVICE_NAME"

for i in $(seq 1 30); do
  if "$PYTHON" - "$HOST" "$PORT" <<'PY'
import json,socket,sys
host=sys.argv[1];port=int(sys.argv[2])
s=socket.create_connection((host,port),1.0)
s.sendall(b'{"op":"health"}\n')
data=b''
while b'\n' not in data:
    part=s.recv(4096)
    if not part: break
    data+=part
s.close()
obj=json.loads(data.split(b'\n',1)[0].decode('utf-8'))
assert obj.get('ok') is True and obj.get('service')=='zonoe-ipa-parser'
PY
  then
    echo "OK ${SERVICE_NAME} active at tcp://${HOST}:${PORT} user=${SERVICE_USER} root=${ROOT}"
    systemctl --no-pager --full status "$SERVICE_NAME" | sed -n '1,12p' || true
    exit 0
  fi
  sleep 0.2
done

echo "ERROR: service started but health check failed" >&2
systemctl --no-pager --full status "$SERVICE_NAME" || true
journalctl -u "$SERVICE_NAME" -n 50 --no-pager || true
exit 1
