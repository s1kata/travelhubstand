<?php
/**
 * Правила и расчёт бонусов для туров (app-side, не зависит от настроек CRM).
 */
declare(strict_types=1);

/**
 * @return array<string, int|float>
 */
function bonus_rules_config(): array
{
    return [
        'bonus_to_rub' => 1,
        'min_discount_pct' => 5,
        'max_discount_pct' => 30,
        'min_bonuses_to_use' => 100,
        'slider_step' => 100,
    ];
}

function bonus_parse_till_date(?string $raw): ?DateTimeImmutable
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $s = trim($raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $s);
        return $dt instanceof DateTimeImmutable ? $dt->setTime(23, 59, 59) : null;
    }
    try {
        return new DateTimeImmutable($s);
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array<int, array<string, mixed>> $transactions
 * @return array{balance: int, availableBalance: int, expiringWithin7Days: int, bcId: int|null}
 */
function bonus_compute_balance_stats(array $transactions): array
{
    $now = new DateTimeImmutable('now');
    $in7 = $now->modify('+7 days');

    $gross = 0;
    $available = 0;
    $expiring7 = 0;
    $bcId = null;

    foreach ($transactions as $t) {
        if (!is_array($t)) {
            continue;
        }
        $amount = (int) ($t['amount'] ?? 0);
        if ($amount <= 0) {
            continue;
        }
        $card = (int) ($t['bcard_id'] ?? 0);
        if ($card > 0 && $bcId === null) {
            $bcId = $card;
        }

        if ((int) ($t['increase'] ?? 0) === 1) {
            $gross += $amount;
            $till = bonus_parse_till_date(isset($t['amount_till_date']) ? (string) $t['amount_till_date'] : null);
            if ($till !== null && $till < $now) {
                continue;
            }
            $available += $amount;
            if ($till !== null && $till <= $in7) {
                $expiring7 += $amount;
            }
        } elseif ((int) ($t['decrease'] ?? 0) === 1) {
            $gross -= $amount;
            $available -= $amount;
        }
    }

    return [
        'balance' => max(0, $gross),
        'availableBalance' => max(0, $available),
        'expiringWithin7Days' => max(0, min($expiring7, max(0, $available))),
        'bcId' => $bcId,
    ];
}

/**
 * @return array<string, mixed>
 */
function bonus_compute_quote(int $tourPrice, int $bonusesToSpend, int $availableBalance): array
{
    $rules = bonus_rules_config();
    $tourPrice = max(0, $tourPrice);
    $bonusesToSpend = max(0, $bonusesToSpend);
    $availableBalance = max(0, $availableBalance);

    if ($tourPrice <= 0) {
        return ['success' => false, 'error' => 'Некорректная цена тура'];
    }

    $maxDiscountRub = (int) floor($tourPrice * ((float) $rules['max_discount_pct']) / 100);
    $minDiscountRub = (int) ceil($tourPrice * ((float) $rules['min_discount_pct']) / 100);
    $maxBonuses = min($availableBalance, $maxDiscountRub);
    $minBonuses = min($maxBonuses, max((int) $rules['min_bonuses_to_use'], $minDiscountRub));

    if ($bonusesToSpend === 0) {
        return [
            'success' => true,
            'tourPrice' => $tourPrice,
            'bonusesToSpend' => 0,
            'discountRub' => 0,
            'payableRub' => $tourPrice,
            'maxBonuses' => $maxBonuses,
            'minBonuses' => $maxBonuses > 0 ? $minBonuses : 0,
            'availableBalance' => $availableBalance,
            'rules' => $rules,
        ];
    }

    if ($bonusesToSpend > $maxBonuses) {
        return ['success' => false, 'error' => 'Превышен лимит списания бонусов'];
    }
    if ($maxBonuses > 0 && $bonusesToSpend < $minBonuses) {
        return [
            'success' => false,
            'error' => sprintf(
                'Минимум %d бонусов (от %d%% стоимости тура)',
                $minBonuses,
                (int) $rules['min_discount_pct']
            ),
        ];
    }

    $discountRub = $bonusesToSpend;
    $payableRub = max(0, $tourPrice - $discountRub);

    return [
        'success' => true,
        'tourPrice' => $tourPrice,
        'bonusesToSpend' => $bonusesToSpend,
        'discountRub' => $discountRub,
        'payableRub' => $payableRub,
        'maxBonuses' => $maxBonuses,
        'minBonuses' => $maxBonuses > 0 ? $minBonuses : 0,
        'availableBalance' => $availableBalance,
        'rules' => $rules,
    ];
}

/**
 * @return array<string, int|float>
 */
function bonus_rules_for_client(): array
{
    $r = bonus_rules_config();
    return [
        'bonusToRub' => $r['bonus_to_rub'],
        'minDiscountPct' => $r['min_discount_pct'],
        'maxDiscountPct' => $r['max_discount_pct'],
        'minBonusesToUse' => $r['min_bonuses_to_use'],
        'sliderStep' => $r['slider_step'],
    ];
}
