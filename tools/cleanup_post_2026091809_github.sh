#!/usr/bin/env bash
set -euo pipefail

REPO="${REPO:-a7987083/app-}"
BASE_VERSION="2026091809"
APPLY="${APPLY:-0}"

log() { printf '%s\n' "$*"; }
run() {
  if [ "$APPLY" = "1" ]; then
    "$@"
  else
    printf '[DRY-RUN]'; printf ' %q' "$@"; printf '\n'
  fi
}

command -v gh >/dev/null || { echo 'gh is required'; exit 1; }
gh auth status >/dev/null

log "Repository: $REPO"
log "Keep baseline: source-v$BASE_VERSION"
log "Mode: $([ "$APPLY" = "1" ] && echo APPLY || echo DRY-RUN)"

# Delete releases/tags newer than the selected baseline.
while IFS= read -r tag; do
  version="${tag#source-v}"
  case "$version" in
    ''|*[!0-9]*) continue ;;
  esac
  if [ "$version" -gt "$BASE_VERSION" ]; then
    log "retire release/tag: $tag"
    run gh release delete "$tag" --repo "$REPO" --cleanup-tag --yes
  fi
done < <(gh release list --repo "$REPO" --limit 200 --json tagName --jq '.[].tagName')

# Delete versioned branches whose embedded YYYYMMDDNN version is newer than 2026091809.
while IFS= read -r branch; do
  version="$(printf '%s\n' "$branch" | grep -oE '20[0-9]{8}' | head -n1 || true)"
  if [ -n "$version" ] && [ "$version" -gt "$BASE_VERSION" ]; then
    log "retire branch: $branch"
    run gh api --method DELETE "repos/$REPO/git/refs/heads/$branch"
  fi
done < <(gh api --paginate "repos/$REPO/branches?per_page=100" --jq '.[].name')

# Phase 20 branches may not contain a numeric release version; they are also outside the 1809 baseline.
while IFS= read -r branch; do
  case "$branch" in
    *phase20*|*ipa-management*|*ipa-workset*|*persistent-ipa-worker*|*persistent-parser-service*|*worker-health-refresh*|*scan-json-only*)
      log "retire Phase 20 branch: $branch"
      run gh api --method DELETE "repos/$REPO/git/refs/heads/$branch"
      ;;
  esac
done < <(gh api --paginate "repos/$REPO/branches?per_page=100" --jq '.[].name')

log 'Cleanup scan complete.'
log 'Run again with APPLY=1 only after reviewing the dry-run output.'
