#!/usr/bin/env bash
# Прогрев кэша для стран batch ротации YML (promo + search).
#
# Cron (понедельник 00:10, перед ротацией 00:25):
#   10 0 * * 1 cd /path/to/travelhub-v2 && PHP_BIN=/usr/bin/php8.1 flock -n data/yml_rotation_warm.lock bash backend/cron/warm_yml_rotation_countries.sh >> data/yml_rotation_warm.log 2>&1

# SpaceWeb: без pipefail — CRLF ломает set -o pipefail.
set -eu

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

CRON_SCRIPT="$ROOT/backend/cron/warm_yml_rotation_countries.php"

if [[ ! -f "$CRON_SCRIPT" ]]; then
  echo "Не найден: $CRON_SCRIPT" >&2
  exit 1
fi

resolve_php_bin() {
  if [[ -n "${PHP_BIN:-}" ]] && command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "$PHP_BIN"
    return 0
  fi
  local c major
  for c in /usr/bin/php8.2 /usr/bin/php8.1 /usr/bin/php8.0 /usr/bin/php7.4 php82 php81 php; do
    if ! command -v "$c" >/dev/null 2>&1; then
      continue
    fi
    major=$("$c" -r 'echo (int) PHP_MAJOR_VERSION;' 2>/dev/null || echo 0)
    if [[ "$major" -ge 7 ]]; then
      echo "$c"
      return 0
    fi
  done
  return 1
}

PHP_BIN="$(resolve_php_bin)" || {
  echo "PHP 7+ not found" >&2
  exit 1
}

echo "[$(date '+%Y-%m-%dT%H:%M:%S%z')] yml rotation warm start php=$PHP_BIN"
"$PHP_BIN" "$CRON_SCRIPT"
echo "[$(date '+%Y-%m-%dT%H:%M:%S%z')] yml rotation warm done"
