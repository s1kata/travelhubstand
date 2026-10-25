#!/usr/bin/env bash
# Rebuilds the rolling calendar cache from promo + already warmed search cover.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

CAL_PHP_ARGS=(-d memory_limit=768M)
if [[ -n "${PHP_BIN:-}" ]] && command -v "$PHP_BIN" >/dev/null 2>&1; then
  "$PHP_BIN" "${CAL_PHP_ARGS[@]}" backend/cron/warm_calendar_cache.php
elif command -v php8.1 >/dev/null 2>&1; then
  php8.1 "${CAL_PHP_ARGS[@]}" backend/cron/warm_calendar_cache.php
else
  php "${CAL_PHP_ARGS[@]}" backend/cron/warm_calendar_cache.php
fi
