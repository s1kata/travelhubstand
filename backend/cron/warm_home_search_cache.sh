#!/usr/bin/env bash
# Прогрев search-cached для главной (см. warm_home_search_cache.php).
# SpaceWeb: если set -o pipefail ругается — файл был с CRLF; этот скрипт без pipefail.
set -eu

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

resolve_php_bin() {
  if [[ -n "${PHP_BIN:-}" ]] && command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "$PHP_BIN"
    return 0
  fi
  for c in /usr/bin/php8.2 /usr/bin/php8.1 /usr/bin/php8.0 /usr/bin/php7.4 \
           php82 php81 php80 php74 php7.4 php; do
    if command -v "$c" >/dev/null 2>&1; then
      ver="$("$c" -r 'echo PHP_MAJOR_VERSION;' 2>/dev/null || echo 0)"
      if [[ "$ver" =~ ^[0-9]+$ ]] && (( ver >= 7 )); then
        echo "$c"
        return 0
      fi
    fi
  done
  return 1
}

PHP_BIN="$(resolve_php_bin)" || {
  echo "Не найден PHP 7+. Укажите: PHP_BIN=/usr/bin/php8.1 bash backend/cron/warm_home_search_cache.sh" >&2
  exit 1
}

echo "[$(date '+%Y-%m-%dT%H:%M:%S%z')] home search warm start php=$PHP_BIN cwd=$ROOT"
"$PHP_BIN" backend/cron/warm_home_search_cache.php
echo "[$(date '+%Y-%m-%dT%H:%M:%S%z')] home search warm done"
