#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  bash tools/legacy_controller_access_audit.sh [LOG_OR_DIR ...]
  bash tools/legacy_controller_access_audit.sh --delete SITE_ROOT [LOG_OR_DIR ...]

Examples:
  bash tools/legacy_controller_access_audit.sh /www/wwwlogs/app3.zonoeios.xyz.log
  bash tools/legacy_controller_access_audit.sh /www/wwwlogs
  bash tools/legacy_controller_access_audit.sh --delete /www/wwwroot/app3.zonoeios.xyz /www/wwwlogs

The script searches access logs for legacy controller URLs. It only deletes
App-mb.php / Index2.php when --delete is requested AND no matching access is
found in the supplied log set.
EOF
}

DELETE_ROOT=''
if [[ "${1:-}" == "--delete" ]]; then
    [[ $# -ge 2 ]] || { usage >&2; exit 64; }
    DELETE_ROOT="$2"
    shift 2
fi

inputs=("$@")
if [[ ${#inputs[@]} -eq 0 ]]; then
    inputs=(/www/wwwlogs)
fi

logs=()
for input in "${inputs[@]}"; do
    if [[ -f "$input" ]]; then
        logs+=("$input")
    elif [[ -d "$input" ]]; then
        while IFS= read -r -d '' file; do
            logs+=("$file")
        done < <(find "$input" -maxdepth 2 -type f \( -name '*.log' -o -name '*.log.*' \) -print0 2>/dev/null)
    fi
done

if [[ ${#logs[@]} -eq 0 ]]; then
    echo "ERROR: 没有找到可审计的访问日志；不会删除任何文件。" >&2
    exit 3
fi

pattern='/(index\.php/)?(index/)?(app-mb|index2)(/|[?[:space:]])|/(App-mb|Index2)\.php([?[:space:]]|$)'
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

if grep -HniE "$pattern" "${logs[@]}" >"$tmp" 2>/dev/null; then
    echo "FOUND: 检测到 legacy controller 访问记录，不执行删除。"
    cat "$tmp"
    exit 2
fi

echo "OK: 在 ${#logs[@]} 个访问日志文件中未发现 App-mb / Index2 访问。"

if [[ -n "$DELETE_ROOT" ]]; then
    controller_dir="${DELETE_ROOT%/}/application/index/controller"
    for file in App-mb.php Index2.php; do
        target="$controller_dir/$file"
        if [[ -f "$target" ]]; then
            rm -f -- "$target"
            echo "REMOVED: $target"
        else
            echo "SKIP: $target 不存在"
        fi
    done
fi
