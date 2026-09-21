#!/usr/bin/env bash
set -euo pipefail

if [ "${EUID:-$(id -u)}" -ne 0 ]; then
  echo "ERROR: run as root: sudo bash scripts/install-ipa-worker-service.sh" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${ZONOE_PHP:-$(command -v php || true)}"
SERVICE_NAME="${ZONOE_IPA_WORKER_SERVICE:-zonoe-ipa-worker.service}"
UNIT="/etc/systemd/system/${SERVICE_NAME}"

if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]; then
  echo "ERROR: php CLI not found; set ZONOE_PHP=/path/to/php" >&2
  exit 1
fi
if [ ! -f "$ROOT/think" ]; then
  echo "ERROR: think CLI not found under $ROOT" >&2
  exit 1
fi
if ! command -v systemctl >/dev/null 2>&1; then
  echo "ERROR: systemctl not found" >&2
  exit 1
fi

if [ -n "${ZONOE_IPA_WORKER_USER:-}" ]; then
  SERVICE_USER="$ZONOE_IPA_WORKER_USER"
else
  OWNER="$(stat -c '%U' "$ROOT" 2>/dev/null || true)"
  if [ -n "$OWNER" ] && [ "$OWNER" != "root" ] && id "$OWNER" >/dev/null 2>&1; then
    SERVICE_USER="$OWNER"
  elif id www >/dev/null 2>&1; then
    SERVICE_USER="www"
  elif id www-data >/dev/null 2>&1; then
    SERVICE_USER="www-data"
  else
    echo "ERROR: cannot determine non-root service user; set ZONOE_IPA_WORKER_USER" >&2
    exit 1
  fi
fi

"$PHP_BIN" -v >/dev/null
"$PHP_BIN" "$ROOT/think" list | grep -q 'ipa:worker'

cat > "$UNIT" <<EOF
[Unit]
Description=ZONOE Persistent IPA Worker
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=${SERVICE_USER}
WorkingDirectory=${ROOT}
ExecStart=${PHP_BIN} ${ROOT}/think ipa:worker --sleep=2
Restart=always
RestartSec=2
TimeoutStopSec=15
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
  if systemctl is-active --quiet "$SERVICE_NAME"; then
    echo "OK ${SERVICE_NAME} active user=${SERVICE_USER} root=${ROOT} php=${PHP_BIN}"
    systemctl --no-pager --full status "$SERVICE_NAME" | sed -n '1,12p' || true
    exit 0
  fi
  sleep 0.2
done

echo "ERROR: worker service failed to become active" >&2
 systemctl --no-pager --full status "$SERVICE_NAME" || true
journalctl -u "$SERVICE_NAME" -n 50 --no-pager || true
exit 1
