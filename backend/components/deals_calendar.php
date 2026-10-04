<?php
/**
 * Календарь выгодных туров: «лестница» месяцев + две метки цен.
 *
 * Лестница: текущий месяц + N вперёд (по умолчанию 3 → из августа до ноября).
 * С каждым новым месяцем окно сдвигается: задний месяц отваливается, впереди
 * появляется следующий — прогрев акций тянет пакет до конца горизонта.
 */
declare(strict_types=1);

/** Сколько месяцев вперёд от текущего (не считая текущий). 3 → авг…ноя. */
function th_deals_calendar_months_ahead(): int
{
    $n = (int) (getenv('TH_DEALS_CAL_MONTHS_AHEAD') ?: ($_ENV['TH_DEALS_CAL_MONTHS_AHEAD'] ?? 3));
    return max(1, min(6, $n));
}

/**
 * @return array{
 *   today: DateTimeImmutable,
 *   horizon: DateTimeImmutable,
 *   monthsAhead: int,
 *   horizonYmd: string,
 *   viewMaxYm: string,
 *   daysAhead: int
 * }
 */
function th_deals_calendar_ladder(?DateTimeImmutable $today = null): array
{
    $today = $today ?? new DateTimeImmutable('today');
    $monthsAhead = th_deals_calendar_months_ahead();
    $horizon = $today
        ->modify('first day of this month')
        ->modify('+' . $monthsAhead . ' months')
        ->modify('last day of this month');
    $daysAhead = max(1, (int) $today->diff($horizon)->days);

    return [
        'today' => $today,
        'horizon' => $horizon,
        'monthsAhead' => $monthsAhead,
        'horizonYmd' => $horizon->format('Y-m-d'),
        'viewMaxYm' => $horizon->format('Y-m'),
        'daysAhead' => $daysAhead,
    ];
}

/**
 * Две метки на все дни с ценой: выгодная (ниже ~40%) и пониженная (остальные).
 *
 * @param array<string, array{minPrice:int, countryId?:int, countryName?:string}> $byDate
 * @return array<string, array{minPrice:int, deal:bool, reduced:bool, countryId?:int, countryName?:string}>
 */
function th_deals_calendar_mark_tiers(array $byDate): array
{
    $prices = [];
    foreach ($byDate as $row) {
        $p = (int) ($row['minPrice'] ?? 0);
        if ($p > 0) {
            $prices[] = $p;
        }
    }
    sort($prices);

    $dealThreshold = 0;
    $n = count($prices);
    if ($n >= 2) {
        $dealThreshold = (int) $prices[(int) floor(($n - 1) * 0.4)];
    } elseif ($n === 1) {
        $dealThreshold = (int) $prices[0];
    }

    $out = [];
    foreach ($byDate as $ymd => $row) {
        $price = (int) ($row['minPrice'] ?? 0);
        $deal = $dealThreshold > 0 && $price > 0 && $price <= $dealThreshold;
        $reduced = $price > 0 && !$deal;
        $out[$ymd] = [
            'minPrice' => $price,
            'deal' => $deal,
            'reduced' => $reduced,
            'countryId' => (int) ($row['countryId'] ?? 0),
            'countryName' => (string) ($row['countryName'] ?? ''),
        ];
    }

    return $out;
}
