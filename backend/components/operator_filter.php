<?php
/**
 * Фильтр туроператоров в выдаче туров (Tourvisor).
 *
 * Логика:
 *  - Общий список операторов — для всех стран, КРОМЕ Турции и Египта.
 *  - Для Турции (Tourvisor countryId = 4) и Египта (countryId = 1) — сокращённый список.
 *
 * Фильтр применяется на этапе обработки данных из API в прокси
 * (backend/components/api/tourvisor-proxy.php), поэтому:
 *  - автоматически переключается при смене страны (по countryId / названию);
 *  - работает и для обычного поиска, и для поиска по акциям;
 *  - кэш и YML-фид сохраняют полную выдачу (фильтруется только ответ клиенту).
 *
 * Совпадение оператора — по нормализованному имени (регистр, пробелы, «&», «-», «'»
 * игнорируются) с набором алиасов (латиница/кириллица) для каждого бренда.
 */
declare(strict_types=1);

/**
 * Общий список операторов (все страны, кроме Турции и Египта).
 * Каждый элемент — набор алиасов одного бренда.
 *
 * @var array<int, list<string>>
 */
const TH_OPERATOR_ALIASES_GENERAL = [
    ['Fun Sun', 'Fun&Sun', 'FunSun', 'Фансан', 'Фан Сан'],
    ['Anex', 'Anex Tour', 'Анекс'],
    ['Coral', 'Coral Travel', 'Корал'],
    ['Sunmar', 'Санмар'],
    ['Pegas', 'Pegas Touristik', 'Пегас'],
    ['Русский экспресс', 'Russian Express', 'Russ Express'],
    ['Loti', 'Лоти'],
    ['Библио глобус', 'Библио-Глобус', 'Biblio Globus', 'BiblioGlobus'],
    ['Paks', 'Пакс'],
    ["Let's fly", 'Lets fly', 'Летс флай', 'ЛетсФлай'],
    ['Интурист', 'Intourist'],
    ['Амботис', 'Ambotis'],
];

/** Виртуальная плитка «Фукуок» + Tourvisor regionId курорта. */
const TH_OPERATOR_PHUQUOC_VIRTUAL_COUNTRY_ID = 16104;
const TH_OPERATOR_PHUQUOC_REGION_ID = 104;

/**
 * Space Travel только для Самара→Фукуок (не для остальных стран сайта).
 *
 * @param array{departureId?:int,countryId?:int,regionIds?:string,promoTileId?:int} $ctx
 */
function th_operator_allows_space_travel(array $ctx): bool
{
    $departureId = (int) ($ctx['departureId'] ?? 0);
    if ($departureId !== 7) {
        return false;
    }
    $countryId = (int) ($ctx['countryId'] ?? 0);
    $promoTileId = (int) ($ctx['promoTileId'] ?? 0);
    if ($countryId === TH_OPERATOR_PHUQUOC_VIRTUAL_COUNTRY_ID || $promoTileId === TH_OPERATOR_PHUQUOC_VIRTUAL_COUNTRY_ID) {
        return true;
    }
    $regionRaw = trim((string) ($ctx['regionIds'] ?? ''));
    if ($regionRaw === '') {
        return false;
    }
    $regionIds = array_map('intval', array_filter(explode(',', $regionRaw)));

    return in_array(TH_OPERATOR_PHUQUOC_REGION_ID, $regionIds, true);
}

/** @return list<string> */
function th_operator_space_travel_aliases(): array
{
    return ['Space Travel', 'SpaceTravel', 'Спейс Тревел', 'СпейсТревел', 'Спейс трэвел'];
}

/**
 * Сокращённый список для Турции и Египта.
 *
 * @var array<int, list<string>>
 */
const TH_OPERATOR_ALIASES_TURKEY_EGYPT = [
    ['Fun Sun', 'Fun&Sun', 'FunSun', 'Фансан'],
    ['Coral', 'Coral Travel', 'Корал'],
    ['Anex', 'Anex Tour', 'Анекс'],
    ['Sunmar', 'Санмар'],
    ['Pegas', 'Pegas Touristik', 'Пегас'],
    ['Интурист', 'Intourist'],
    ['Библио глобус', 'Библио-Глобус', 'Biblio Globus', 'BiblioGlobus'],
];

/** ID стран Турция/Египет в Tourvisor (см. backend/config/country_promo_tourvisor_map.php). */
const TH_OPERATOR_TURKEY_EGYPT_COUNTRY_IDS = [1, 4];

/**
 * Нормализация имени: нижний регистр + только буквы/цифры (Unicode).
 * «Fun&Sun», «FUN SUN», «fun-sun» → «funsun».
 */
function th_operator_normalize(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);

    return $s ?? '';
}

/** Турция или Египет: по ID страны либо по названию. */
function th_operator_is_turkey_or_egypt(int $countryId, string $countryName = ''): bool
{
    if ($countryId > 0 && in_array($countryId, TH_OPERATOR_TURKEY_EGYPT_COUNTRY_IDS, true)) {
        return true;
    }
    $n = th_operator_normalize($countryName);
    if ($n === '') {
        return false;
    }
    foreach (['турция', 'turkey', 'turkiye', 'türkiye', 'египет', 'egypt'] as $needle) {
        $needle = th_operator_normalize($needle);
        if ($needle !== '' && strpos($n, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Нормализованные алиасы операторов для выбранной страны.
 *
 * @param array{allowSpaceTravel?:bool} $opts
 * @return list<string>
 */
function th_operator_allowed_tokens(int $countryId, string $countryName = '', array $opts = []): array
{
    $groups = th_operator_is_turkey_or_egypt($countryId, $countryName)
        ? TH_OPERATOR_ALIASES_TURKEY_EGYPT
        : TH_OPERATOR_ALIASES_GENERAL;

    $tokens = [];
    foreach ($groups as $aliases) {
        foreach ($aliases as $alias) {
            $norm = th_operator_normalize((string) $alias);
            if ($norm !== '') {
                $tokens[$norm] = true;
            }
        }
    }
    if (!empty($opts['allowSpaceTravel'])) {
        foreach (th_operator_space_travel_aliases() as $alias) {
            $norm = th_operator_normalize($alias);
            if ($norm !== '') {
                $tokens[$norm] = true;
            }
        }
    }

    return array_keys($tokens);
}

/** Имя туроператора из тура (Tourvisor: operatorName либо operator: строка/объект). */
function th_operator_tour_label(array $tour): string
{
    if (isset($tour['operatorName']) && is_string($tour['operatorName'])) {
        return trim($tour['operatorName']);
    }
    if (isset($tour['operator'])) {
        if (is_string($tour['operator'])) {
            return trim($tour['operator']);
        }
        if (is_array($tour['operator'])) {
            foreach (['name', 'russianName', 'title'] as $k) {
                if (isset($tour['operator'][$k]) && is_string($tour['operator'][$k]) && trim($tour['operator'][$k]) !== '') {
                    return trim($tour['operator'][$k]);
                }
            }
        }
    }

    return '';
}

/**
 * Разрешён ли оператор.
 * Пустое имя оператора считаем разрешённым (не прячем тур без данных об операторе).
 *
 * @param list<string> $allowedTokens
 */
function th_operator_label_allowed(string $label, array $allowedTokens): bool
{
    $norm = th_operator_normalize($label);
    if ($norm === '') {
        return true;
    }
    foreach ($allowedTokens as $token) {
        if ($token === '') {
            continue;
        }
        if (strpos($norm, $token) !== false || strpos($token, $norm) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Фильтрует список отелей: у каждого отеля оставляет только туры разрешённых операторов.
 * Отель без подходящих туров исключается. Отели без массива tours не трогаются.
 *
 * @param array<int, mixed> $hotels
 * @param array{allowSpaceTravel?:bool} $opts
 * @return list<mixed>
 */
function th_operator_filter_hotels(array $hotels, int $countryId, string $countryName = '', array $opts = []): array
{
    $allowedTokens = th_operator_allowed_tokens($countryId, $countryName, $opts);
    if ($allowedTokens === []) {
        return array_values($hotels);
    }

    $out = [];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel) || !isset($hotel['tours']) || !is_array($hotel['tours'])) {
            $out[] = $hotel;
            continue;
        }
        $kept = [];
        foreach ($hotel['tours'] as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            if (th_operator_label_allowed(th_operator_tour_label($tour), $allowedTokens)) {
                $kept[] = $tour;
            }
        }
        if ($kept === []) {
            continue;
        }
        $hotel['tours'] = array_values($kept);
        $out[] = $hotel;
    }

    return array_values($out);
}
