<?php
/**
 * Виртуальные направления на странице акций (Фукуок и др.).
 */
declare(strict_types=1);

require_once __DIR__ . '/promo_sochi_filter.php';

/** @return array<int, array{label: string, tvCountryId: int, regionId?: int, regionIds?: int[]}> */
function th_promo_virtual_destinations_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = dirname(__DIR__) . '/config/promo_virtual_destinations.php';
    $cfg = is_file($path) ? require $path : [];
    if (!is_array($cfg)) {
        $cfg = [];
    }

    return $cfg;
}

function th_promo_is_virtual_country_id(int $countryId): bool
{
    return isset(th_promo_virtual_destinations_config()[$countryId]);
}

/** @return array{label: string, tvCountryId: int, regionId?: int, regionIds?: int[]}|null */
function th_promo_virtual_destination_config(int $countryId): ?array
{
    $cfg = th_promo_virtual_destinations_config()[$countryId] ?? null;

    return is_array($cfg) ? $cfg : null;
}

/** Id страны TourVisor для API-поиска (для виртуальных — базовая страна). */
function th_promo_resolve_tv_country_id(int $countryId): int
{
    $cfg = th_promo_virtual_destination_config($countryId);
    if ($cfg !== null && !empty($cfg['tvCountryId'])) {
        return (int) $cfg['tvCountryId'];
    }

    return $countryId;
}

/** @return int[] */
function th_promo_virtual_region_ids(int $countryId): array
{
    $cfg = th_promo_virtual_destination_config($countryId);
    if ($cfg === null) {
        return [];
    }
    if (!empty($cfg['regionIds']) && is_array($cfg['regionIds'])) {
        return array_values(array_filter(array_map('intval', $cfg['regionIds']), static fn (int $v): bool => $v > 0));
    }
    if (!empty($cfg['regionId'])) {
        return [(int) $cfg['regionId']];
    }

    return [];
}

function th_promo_virtual_display_label(int $countryId): ?string
{
    $cfg = th_promo_virtual_destination_config($countryId);

    return ($cfg && !empty($cfg['label'])) ? (string) $cfg['label'] : null;
}

/**
 * Параметры search-cached / promo для dispatch: tv countryId + regionIds.
 *
 * @param array<string, string> $params
 * @return array<string, string>
 */
function th_promo_apply_virtual_search_params(int $promoCountryId, array $params): array
{
    if (!th_promo_is_virtual_country_id($promoCountryId)) {
        return $params;
    }
    $params['promoTileId'] = (string) $promoCountryId;
    $params['countryId'] = (string) th_promo_resolve_tv_country_id($promoCountryId);
    $regionIds = th_promo_virtual_region_ids($promoCountryId);
    if ($regionIds !== []) {
        $params['regionIds'] = implode(',', array_map('strval', $regionIds));
    }
    // Иначе вложенный onlyPromo читает promo_cache_{vietnam} без Фукуока
    $params['live'] = '1';
    $params['bypassCache'] = '1';

    return $params;
}

/** Эффективный id для правил ночей/дат (виртуальные → как базовая страна). */
function th_promo_effective_rules_country_id(int $countryId): int
{
    if (th_promo_is_virtual_country_id($countryId)) {
        return th_promo_resolve_tv_country_id($countryId);
    }

    return $countryId;
}

/** @return array<string, array{label: string, tvCountryId: int, regionId?: int}> */
function th_promo_virtual_destinations_for_frontend(): array
{
    $out = [];
    foreach (th_promo_virtual_destinations_config() as $id => $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[(string) $id] = [
            'label' => (string) ($row['label'] ?? ''),
            'tvCountryId' => (int) ($row['tvCountryId'] ?? 0),
            'regionId' => isset($row['regionId']) ? (int) $row['regionId'] : null,
        ];
    }

    return $out;
}
