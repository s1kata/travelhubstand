<?php
/**
 * Плитка «Сочи» на акциях = Tourvisor countryId 47 (Россия), в выдаче оставляем только курорты Сочи.
 */
declare(strict_types=1);

/** Tourvisor countryId для плитки «Сочи». */
function th_promo_sochi_country_id(): int
{
    return 47;
}

function th_promo_hotel_region_label(array $hotel): string
{
    $parts = [];
    if (!empty($hotel['region']) && is_array($hotel['region'])) {
        $parts[] = trim((string) ($hotel['region']['name'] ?? $hotel['region']['russianName'] ?? ''));
    }
    if (!empty($hotel['region']) && is_string($hotel['region'])) {
        $parts[] = trim($hotel['region']);
    }
    if (!empty($hotel['regionName'])) {
        $parts[] = trim((string) $hotel['regionName']);
    }
    if (!empty($hotel['city'])) {
        $parts[] = trim(is_array($hotel['city']) ? (string) ($hotel['city']['name'] ?? '') : (string) $hotel['city']);
    }
    if (!empty($hotel['resort'])) {
        $parts[] = trim(is_array($hotel['resort']) ? (string) ($hotel['resort']['name'] ?? '') : (string) $hotel['resort']);
    }

    return trim(implode(' ', array_filter($parts, static fn ($p) => $p !== '')));
}

function th_promo_region_is_sochi_destination(string $region): bool
{
    $region = trim($region);
    if ($region === '') {
        return false;
    }
    if (preg_match('/\b(москва|московск|подмосков|санкт-петербург|петербург|спб|казань|новосибирск|екатеринбург|нижний\s+новгород|воронеж|ростов|красноярск|уфа|пермь|самара|волгоград|краснодар|калининград|мурманск|тюмень|омск|челябинск|иркутск|хабаровск|владивосток|тула|ярославль|смоленск|брянск|калуга|владимир|рязань|архангельск|псков|петрозаводск|великий\s+новгород)\b/ui', $region)) {
        return false;
    }

    $sochiResortPattern = '/\b(сочи|sochi|адлер|adler|хоста|лазаревск|лоо|дагомыс|мацеста|кудепста|красная\s*поляна|роза\s*хутор|эсто-?садок|имеретинск|имеретинский|сириус|olymp|олимпийск)\b/ui';

    return preg_match($sochiResortPattern, $region) === 1;
}

function th_promo_phuquoc_virtual_country_id(): int
{
    return 16104;
}

function th_promo_vietnam_country_id(): int
{
    return 16;
}

function th_promo_is_vietnam_promo_country_id(int $countryId): bool
{
    return in_array($countryId, [th_promo_vietnam_country_id(), 18], true);
}

function th_promo_region_is_phuquoc_destination(string $region): bool
{
    $region = trim($region);
    if ($region === '') {
        return false;
    }

    /* Tourvisor: Phu Quoc / Фукуок / PhuQuoc Island / Фу Куок */
    return preg_match('/фу\s*к\s*уок|фукуок|phu\s*quoc|phuquoc|phú\s*quốc/ui', $region) === 1;
}

/**
 * @param list<array<string, mixed>> $hotels
 * @return list<array<string, mixed>>
 */
function th_promo_filter_hotels_phuquoc_only(array $hotels): array
{
    $out = [];
    foreach ($hotels as $h) {
        if (!is_array($h)) {
            continue;
        }
        if (th_promo_region_is_phuquoc_destination(th_promo_hotel_region_label($h))) {
            $out[] = $h;
        }
    }

    return $out;
}

/**
 * Плитка «Вьетнам»: без Фукуок (отдельная плитка 16104).
 *
 * @param list<array<string, mixed>> $hotels
 * @return list<array<string, mixed>>
 */
function th_promo_filter_hotels_vietnam_exclude_phuquoc(array $hotels): array
{
    $out = [];
    foreach ($hotels as $h) {
        if (!is_array($h)) {
            continue;
        }
        if (!th_promo_region_is_phuquoc_destination(th_promo_hotel_region_label($h))) {
            $out[] = $h;
        }
    }

    return $out;
}

function th_promo_turkey_country_id(): int
{
    return 4;
}

function th_promo_region_is_turkey_resort_destination(string $region): bool
{
    $region = trim($region);
    if ($region === '') {
        return true;
    }
    /* Акции по Турции: городские направления (прежде всего Стамбул) не показываем. */
    if (preg_match('/\b(istanbul|стамбул)\b/ui', $region)) {
        return false;
    }

    return true;
}

/**
 * @param list<array<string, mixed>> $hotels
 * @return list<array<string, mixed>>
 */
function th_promo_filter_hotels_sochi_resort_only(array $hotels): array
{
    $out = [];
    foreach ($hotels as $h) {
        if (!is_array($h)) {
            continue;
        }
        if (th_promo_region_is_sochi_destination(th_promo_hotel_region_label($h))) {
            $out[] = $h;
        }
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $hotels
 * @return list<array<string, mixed>>
 */
function th_promo_filter_hotels_for_promo_country(array $hotels, int $countryId): array
{
    if ($countryId === th_promo_phuquoc_virtual_country_id()) {
        return th_promo_filter_hotels_phuquoc_only($hotels);
    }
    if (th_promo_is_vietnam_promo_country_id($countryId)) {
        return th_promo_filter_hotels_vietnam_exclude_phuquoc($hotels);
    }
    if ($countryId === th_promo_sochi_country_id()) {
        return th_promo_filter_hotels_sochi_resort_only($hotels);
    }
    if ($countryId === th_promo_turkey_country_id()) {
        $out = [];
        foreach ($hotels as $h) {
            if (!is_array($h)) {
                continue;
            }
            if (th_promo_region_is_turkey_resort_destination(th_promo_hotel_region_label($h))) {
                $out[] = $h;
            }
        }
        return $out;
    }

    return $hotels;
}
