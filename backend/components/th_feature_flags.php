<?php
declare(strict_types=1);
/**
 * Флаги отката поведения (prod): в .env 0/false/off — выключить задачу.
 */

function th_feature_env_raw(string $key): ?string
{
    $v = getenv($key);
    if ($v !== false && trim((string) $v) !== '') {
        return trim((string) $v);
    }
    if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
        return trim((string) $_ENV[$key]);
    }

    return null;
}

/** true = включено (по умолчанию), false только при явном 0/false/off/no */
function th_feature_enabled(string $key, bool $default = true): bool
{
    try {
        $raw = th_feature_env_raw($key);
        if ($raw === null) {
            return $default;
        }
        $s = strtolower($raw);
        if (in_array($s, ['0', 'false', 'off', 'no', 'disabled', 'disable'], true)) {
            return false;
        }
        if (in_array($s, ['1', 'true', 'on', 'yes', 'enabled', 'enable'], true)) {
            return true;
        }

        return $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function th_feature_yml_rule_hotel_stars_filter(): bool
{
    return th_feature_enabled('TH_FEATURE_YML_RULE_HOTEL_STARS_FILTER', true);
}

/** Прямой рейс в акциях: Таиланд (2), Шри-Ланка (12), Фукуок (16104). Остальные — без фильтра. */
function th_feature_promo_direct_flights_thailand_vietnam(): bool
{
    return th_feature_enabled('TH_FEATURE_PROMO_DIRECT_FLIGHTS_TH_VN', true);
}

function th_feature_promo_vietnam_nearest_fallback(): bool
{
    return th_feature_enabled('TH_FEATURE_PROMO_VIETNAM_NEAREST_FALLBACK', true);
}
