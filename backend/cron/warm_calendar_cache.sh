#!/usr/bin/env bash
# Rebuilds the rolling calendar cache from promo + already warmed search cover.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

if [[ -n "${PHP_BIN:-}" ]] && command -v "$PHP_BIN" >/dev/null 2>&1; then
  "$PHP_BIN" backend/cron/warm_calendar_cache.php
elif command -v php8.1 >/dev/null 2>&1; then
  php8.1 backend/cron/warm_calendar_cache.php
else
  php backend/cron/warm_calendar_cache.php
fi
