#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -lt 1 ] || [ "$#" -gt 3 ]; then
  cat >&2 <<'EOF'
Usage:
  tools/trace_monitor_probe.sh <base-url-or-appstore-url> [adder-udid] [cracker-udid]

Examples:
  tools/trace_monitor_probe.sh https://app3.example.com
  tools/trace_monitor_probe.sh https://app3.example.com/appstore 00008120-001A55A93AF0201E 00008110-001229DE2E82802E
EOF
  exit 2
fi

BASE_URL="${1%/}"
ADDER_UDID="${2:-00008120-001A55A93AF0201E}"
CRACKER_UDID="${3:-00008110-001229DE2E82802E}"

case "$BASE_URL" in
  */appstore) ENDPOINT="$BASE_URL" ;;
  *) ENDPOINT="$BASE_URL/appstore" ;;
esac

valid_udid_length() {
  local n="${#1}"
  [ "$n" -eq 25 ] || [ "$n" -eq 40 ]
}

if ! valid_udid_length "$ADDER_UDID"; then
  echo "adder UDID length must be 25 or 40; got ${#ADDER_UDID}" >&2
  exit 2
fi
if ! valid_udid_length "$CRACKER_UDID"; then
  echo "cracker UDID length must be 25 or 40; got ${#CRACKER_UDID}" >&2
  exit 2
fi

TRACE="$(printf '%s|%s' "$ADDER_UDID" "$CRACKER_UDID" | base64 | tr -d '\r\n')"

printf 'Endpoint: %s\n' "$ENDPOINT"
printf 'Adder:    %s (%s chars)\n' "$ADDER_UDID" "${#ADDER_UDID}"
printf 'Cracker:  %s (%s chars)\n' "$CRACKER_UDID" "${#CRACKER_UDID}"
printf 'Decoded:  %s|%s\n' "$ADDER_UDID" "$CRACKER_UDID"
printf 'Base64:   %s\n' "$TRACE"
printf '\n--- HTTP response ---\n'

curl -sS -i \
  -X POST "$ENDPOINT" \
  -H 'Content-Type: application/json' \
  --data-binary "{\"value\":\"$TRACE\"}"

printf '\n'
