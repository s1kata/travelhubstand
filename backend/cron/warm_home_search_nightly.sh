#!/usr/bin/env bash
# Поэтапный ночной cover-warm: часть popular_countries за прогон (месячный горизонт).
# SpaceWeb: PHP_BIN=/usr/bin/php8.1 bash backend/cron/warm_home_search_nightly.sh
# Файл только LF (CRLF ломает bash на Linux).
set -eu

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

resolve_php_bin() {
  if [[ -n "${PHP_BIN:-}" ]] && command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "$PHP_BIN"
    return 0
  fi
  for c in /usr/bin/php8.2 /usr/bin/php8.1 /usr/bin/php8.0 php81 php; do
    if command -v "$c" >/dev/null 2>&1; then
      echo "$c"
      return 0
    fi
  done
  return 1
}

PHP_BIN="$(resolve_php_bin)" || {
  echo "Не найден PHP. Укажите PHP_BIN=/usr/bin/php8.1" >&2
  exit 1
}

PHASE_COUNT="${TH_WARM_PHASE_COUNT:-3}"
PHASE="${WARM_NIGHTLY_PHASE:-${TH_WARM_PHASE:-auto}}"
if [[ "$PHASE" == "auto" || -z "$PHASE" ]]; then
  HOUR="$(date +%H 2>/dev/null | sed 's/^0//')"
  case "$HOUR" in
    1) PHASE=0 ;;
    2) PHASE=1 ;;
    3) PHASE=2 ;;
    *) PHASE=$(( $(date +%j) % PHASE_COUNT )) ;;
  esac
fi

export TH_WARM_SCOPE=nightly
export TH_WARM_PHASE_COUNT="$PHASE_COUNT"
export TH_WARM_PHASE="$PHASE"
export TH_HOME_COVER_DAYS="${TH_HOME_COVER_DAYS:-30}"
export TH_HOME_COVER_PRIORITY_DAYS="${TH_HOME_COVER_PRIORITY_DAYS:-30}"
export TH_WARM_MAX_LIVE_CHUNKS="${TH_WARM_MAX_LIVE_CHUNKS:-120}"

LOCK="${ROOT}/data/search_warm_nightly.lock"
LOG="${ROOT}/data/home_search_warm_nightly.log"
mkdir -p "${ROOT}/data"

echo "[$(date '+%Y-%m-%dT%H:%M:%S%z')] nightly search warm start phase=$PHASE count=$PHASE_COUNT php=$PHP_BIN" >> "$LOG"
if flock -n "$LOCK" "$PHP_BIN" backend/cron/warm_home_search_cache.php >> "$LOG" 2>&1; then
  echo "[$(date '+%Y-%m-%dT%H:%M:%S%z')] nightly search warm done" >> "$LOG"
else
  ec=$?
  if [[ $ec -eq 1 ]]; then
    echo "[$(date '+%Y-%m-%dT%H:%M:%S%z')] nightly search warm skipped (lock busy)" >> "$LOG"
    exit 0
  fi
  exit "$ec"
fi
