#!/usr/bin/env bash
set -euo pipefail

if [ "${EUID:-$(id -u)}" -ne 0 ]; then
  echo "ERROR: run as root: sudo bash scripts/zonoe-server-bootstrap.sh" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "== ZONOE server bootstrap =="
echo "root: $ROOT"

if ! command -v python3 >/dev/null 2>&1; then
  echo "ERROR: python3 not found" >&2
  exit 1
fi
if ! command -v php >/dev/null 2>&1 && [ -z "${ZONOE_PHP:-}" ]; then
  echo "ERROR: php CLI not found; set ZONOE_PHP=/path/to/php" >&2
  exit 1
fi

bash scripts/install-ipa-parser-service.sh
bash scripts/install-ipa-worker-service.sh

echo
echo "== Service health =="
systemctl is-active --quiet zonoe-ipa-parser.service && echo "OK parser: active" || { echo "FAIL parser" >&2; exit 1; }
systemctl is-active --quiet zonoe-ipa-worker.service && echo "OK worker: active" || { echo "FAIL worker" >&2; exit 1; }

python3 - <<'PY'
import json,socket
s=socket.create_connection(('127.0.0.1',19191),1.5)
s.sendall(b'{"op":"health"}\n')
data=b''
while b'\n' not in data:
    p=s.recv(4096)
    if not p: break
    data+=p
s.close()
obj=json.loads(data.split(b'\n',1)[0].decode('utf-8'))
assert obj.get('ok') is True and obj.get('service') == 'zonoe-ipa-parser'
print('OK parser RPC:', obj.get('service'), 'v'+str(obj.get('version','')))
PY

echo
echo "Bootstrap complete. Future server migrations only need this command after files/database are restored:"
echo "  sudo bash scripts/zonoe-server-bootstrap.sh"
