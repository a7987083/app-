#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT="$ROOT/tools/legacy_controller_access_audit.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/site/application/index/controller"
touch "$TMP/site/application/index/controller/App-mb.php"
touch "$TMP/site/application/index/controller/Index2.php"
printf '%s\n' '127.0.0.1 - - [11/Sep/2026:07:00:00 +0800] "GET /appstore HTTP/1.1" 200 10' >"$TMP/access.log"

bash "$SCRIPT" --delete "$TMP/site" "$TMP/access.log" >/dev/null
[[ ! -e "$TMP/site/application/index/controller/App-mb.php" ]]
[[ ! -e "$TMP/site/application/index/controller/Index2.php" ]]

touch "$TMP/site/application/index/controller/App-mb.php"
touch "$TMP/site/application/index/controller/Index2.php"
printf '%s\n' '127.0.0.1 - - [11/Sep/2026:07:01:00 +0800] "GET /index/index2/index HTTP/1.1" 200 10' >"$TMP/access.log"

set +e
bash "$SCRIPT" --delete "$TMP/site" "$TMP/access.log" >/dev/null
status=$?
set -e
[[ $status -eq 2 ]]
[[ -e "$TMP/site/application/index/controller/App-mb.php" ]]
[[ -e "$TMP/site/application/index/controller/Index2.php" ]]

echo "OK legacy_controller_audit_test"
