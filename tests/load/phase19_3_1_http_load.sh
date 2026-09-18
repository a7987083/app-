#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

MYSQL_HOST="${ZONOE_MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${ZONOE_MYSQL_PORT:-3306}"
MYSQL_USER="${ZONOE_MYSQL_USER:-root}"
MYSQL_PASSWORD="${ZONOE_MYSQL_PASSWORD:-root}"
MYSQL_DATABASE="${ZONOE_MYSQL_DATABASE:-zonoe_load}"
BENCH_SECONDS="${ZONOE_LOAD_SECONDS:-2}"
RESULT_FILE="${ZONOE_LOAD_RESULT:-$ROOT/phase19-3-1-load-results.tsv}"
NGINX_CONF="/tmp/zonoe-phase19-3-1-nginx.conf"
NGINX_PID="/tmp/zonoe-phase19-3-1-nginx.pid"
NGINX_LOG="/tmp/zonoe-phase19-3-1-nginx-error.log"
BKEY_FILE="/tmp/zonoe-phase19-3-1-bkey.json"
KEYSTREAM_DIR="/tmp/zonoe-phase19-3-1-keystream"
PHP_PIDS=()
PORTS=(18101 18102 18103 18104 18105 18106 18107 18108)

mysql_cmd() {
  MYSQL_PWD="$MYSQL_PASSWORD" mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" "$MYSQL_DATABASE" "$@"
}

cleanup() {
  set +e
  if [[ -f "$NGINX_PID" ]]; then
    kill "$(cat "$NGINX_PID")" >/dev/null 2>&1 || true
  fi
  for pid in "${PHP_PIDS[@]:-}"; do
    kill "$pid" >/dev/null 2>&1 || true
  done
}
trap cleanup EXIT

cat > .env <<EOF
[database]
type = mysql
hostname = $MYSQL_HOST
database = $MYSQL_DATABASE
username = $MYSQL_USER
password = $MYSQL_PASSWORD
hostport = $MYSQL_PORT
charset = utf8mb4
prefix = fa_
debug = false
EOF

mysql_cmd < tests/fixtures/phase19_3_1_load_schema.sql
mysql_cmd < release/sql/2026091803_api_center_performance.sql

mkdir -p "$KEYSTREAM_DIR"
php -r '$f=getenv("BKEY_FILE"); $p=["version"=>1,"fetched_at"=>time(),"bkey"=>base64_encode("phase1931-benchmark-key")]; file_put_contents($f,json_encode($p));' BKEY_FILE="$BKEY_FILE"

cat > "$NGINX_CONF" <<EOF
pid $NGINX_PID;
error_log $NGINX_LOG notice;
events { worker_connections 2048; }
http {
  access_log off;
  sendfile on;
  keepalive_timeout 10;
  proxy_buffering off;
  upstream zonoe_php {
    server 127.0.0.1:18101;
    server 127.0.0.1:18102;
    server 127.0.0.1:18103;
    server 127.0.0.1:18104;
    server 127.0.0.1:18105;
    server 127.0.0.1:18106;
    server 127.0.0.1:18107;
    server 127.0.0.1:18108;
    keepalive 64;
  }
  server {
    listen 127.0.0.1:18080;
    client_max_body_size 2m;
    location / {
      proxy_pass http://zonoe_php;
      proxy_http_version 1.1;
      proxy_set_header Connection "";
      proxy_set_header Host 127.0.0.1:18080;
      proxy_connect_timeout 5s;
      proxy_read_timeout 30s;
      proxy_send_timeout 30s;
    }
  }
}
EOF

for port in "${PORTS[@]}"; do
  SOURCE_ENCRYPTION_PROVIDER=local \
  SOURCE_LEGACY_BKEY_CACHE_FILE="$BKEY_FILE" \
  SOURCE_LEGACY_KEYSTREAM_CACHE_DIR="$KEYSTREAM_DIR" \
  SOURCE_HTTP_GZIP=0 \
  SOURCE_PERF_LOG=0 \
  php -d display_errors=0 -S "127.0.0.1:$port" -t public public/index.php >"/tmp/zonoe-php-$port.log" 2>&1 &
  PHP_PIDS+=("$!")
done

nginx -c "$NGINX_CONF"

for i in $(seq 1 80); do
  if curl -fsS --max-time 2 "http://127.0.0.1:18080/appstore" >/dev/null 2>&1; then
    break
  fi
  if [[ "$i" == "80" ]]; then
    echo "HTTP load fixture failed to start" >&2
    cat "$NGINX_LOG" >&2 || true
    for port in "${PORTS[@]}"; do tail -50 "/tmp/zonoe-php-$port.log" >&2 || true; done
    exit 1
  fi
  sleep 0.25
done

printf 'scenario\tapps\tmode\tauth\tconcurrency\trequests\trps\tp50_ms\tp95_ms\tp99_ms\terrors\tbody_bytes\tcold_ms\thot_ms\n' > "$RESULT_FILE"

clear_runtime_cache() {
  rm -rf runtime/cache/* runtime/temp/* 2>/dev/null || true
  mkdir -p runtime/cache runtime/temp
}

set_catalog() {
  local apps="$1"
  mysql_cmd -e "UPDATE fa_category SET status=IF(id <= $apps,'normal','hidden');"
  mysql_cmd -e "TRUNCATE TABLE fa_api_request_log;"
  clear_runtime_cache
}

set_mode() {
  local mode="$1"
  local opencry=0
  if [[ "$mode" == "encrypted" ]]; then opencry=1; fi
  mysql_cmd -e "UPDATE fa_config SET value='$opencry' WHERE name='opencry';"
  clear_runtime_cache
}

metric_from_line() {
  local line="$1" key="$2"
  echo "$line" | tr ' ' '\n' | awk -F= -v k="$key" '$1==k {print $2; exit}'
}

run_case() {
  local apps="$1" mode="$2" auth="$3" concurrency="$4"
  local scenario="${apps}_${mode}_${auth}_c${concurrency}"
  local url="http://127.0.0.1:18080/appstore"
  if [[ "$auth" == "licensed" ]]; then
    url="$url?udid=LOADTEST-LICENSED-UDID"
  fi

  set_catalog "$apps"
  set_mode "$mode"

  local cold hot body_bytes
  cold="$(curl -fsS --max-time 30 -o "/tmp/${scenario}-cold.body" -w '%{time_total}' "$url")"
  hot="$(curl -fsS --max-time 30 -o "/tmp/${scenario}-hot.body" -w '%{time_total}' "$url")"
  body_bytes="$(wc -c < "/tmp/${scenario}-hot.body" | tr -d ' ')"

  local output line requests duration_us errors p50 p95 p99 rps
  output="$(wrk -t4 -c"$concurrency" -d"${BENCH_SECONDS}s" --latency -s tests/load/wrk_metrics.lua "$url")"
  echo "$output"
  line="$(echo "$output" | grep 'ZONOE_WRK_RESULT' | tail -1)"
  [[ -n "$line" ]] || { echo "Missing wrk metrics for $scenario" >&2; exit 1; }

  requests="$(metric_from_line "$line" requests)"
  duration_us="$(metric_from_line "$line" duration_us)"
  errors="$(metric_from_line "$line" errors)"
  p50="$(metric_from_line "$line" p50_us)"
  p95="$(metric_from_line "$line" p95_us)"
  p99="$(metric_from_line "$line" p99_us)"
  rps="$(awk -v r="$requests" -v d="$duration_us" 'BEGIN { if(d<=0){print "0.00"} else {printf "%.2f", r/(d/1000000)} }')"

  if [[ "${errors:-1}" != "0" ]]; then
    echo "Load scenario $scenario had $errors errors" >&2
    exit 1
  fi
  if [[ "${requests:-0}" -lt "$concurrency" ]]; then
    echo "Load scenario $scenario completed too few requests: $requests" >&2
    exit 1
  fi

  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%.3f\t%.3f\t%.3f\t%s\t%s\t%.3f\t%.3f\n' \
    "$scenario" "$apps" "$mode" "$auth" "$concurrency" "$requests" "$rps" \
    "$(awk -v v="$p50" 'BEGIN {print v/1000}')" \
    "$(awk -v v="$p95" 'BEGIN {print v/1000}')" \
    "$(awk -v v="$p99" 'BEGIN {print v/1000}')" \
    "$errors" "$body_bytes" \
    "$(awk -v v="$cold" 'BEGIN {print v*1000}')" \
    "$(awk -v v="$hot" 'BEGIN {print v*1000}')" >> "$RESULT_FILE"
}

run_case 5000 plain guest 16
run_case 10000 plain guest 16
run_case 20000 plain guest 16
run_case 20000 plain guest 32
run_case 20000 plain licensed 16

run_case 5000 encrypted guest 8
run_case 10000 encrypted guest 8
run_case 20000 encrypted guest 8
run_case 20000 encrypted licensed 8

echo "===== Phase 19.3.1 HTTP load results ====="
column -t -s $'\t' "$RESULT_FILE" || cat "$RESULT_FILE"

php tests/phase19_3_1_load_result_gate.php "$RESULT_FILE"
