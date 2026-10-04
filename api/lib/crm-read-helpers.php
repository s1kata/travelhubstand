<?php
/**
 * Общие хелперы CRM read (U-ON): клиент, бонусы.
 */
declare(strict_types=1);

require_once __DIR__ . '/uon-client.php';

function crm_normalize_phone(string $phone): string
{
    return preg_replace('/\D/', '', $phone) ?? '';
}

/**
 * Проверка, что email/phone из запроса совпадают с JWT (auth-mobile).
 *
 * @param array<string, mixed> $claims
 */
function crm_assert_email_phone_allowed(array $claims, ?string $email, ?string $phone): ?string
{
    $tokenEmail = isset($claims['email']) ? strtolower(trim((string) $claims['email'])) : '';
    if ($email && $tokenEmail && strtolower(trim($email)) !== $tokenEmail) {
        return 'Email does not match signed-in user';
    }
    $tokenPhone = isset($claims['phone_number']) ? crm_normalize_phone((string) $claims['phone_number']) : '';
    $qPhone = $phone ? crm_normalize_phone($phone) : '';
    if ($qPhone && $tokenPhone && $qPhone !== $tokenPhone) {
        return 'Phone does not match signed-in user';
    }
    return null;
}

/**
 * @return array{success: bool, data?: mixed, error?: string}
 */
function crm_uon_get_client_id(array $config, ?string $email, ?string $phone): array
{
    if ($email) {
        $res = uon_request('user/email.json', $config, [
            'method' => 'POST',
            'body' => json_encode(['email' => trim($email)], JSON_UNESCAPED_UNICODE),
        ]);
        if ($res['success'] && isset($res['data']['id'])) {
            return ['success' => true, 'data' => (int) $res['data']['id']];
        }
    }
    if ($phone) {
        $digits = crm_normalize_phone($phone);
        if ($digits !== '') {
            $res = uon_request('user/phone/' . rawurlencode($digits) . '.json', $config);
            if ($res['success'] && isset($res['data']['id'])) {
                return ['success' => true, 'data' => (int) $res['data']['id']];
            }
        }
    }
    return ['success' => true, 'data' => null];
}

/**
 * @return array{success: bool, data?: array<int, array<string, mixed>>, error?: string}
 */
function crm_uon_get_bonus_transactions_by_user(array $config, int $clientId): array
{
    $res = uon_request("bcard-bonus-by-user/{$clientId}.json", $config);
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'Failed to fetch bonus transactions'];
    }
    $raw = $res['data'] ?? [];
    $list = [];
    $isList = is_array($raw) && ($raw === [] || array_keys($raw) === range(0, count($raw) - 1));
    if ($isList) {
        $list = $raw;
    } elseif (is_array($raw)) {
        foreach (['rows', 'row', 'data', 'items'] as $key) {
            if (isset($raw[$key]) && is_array($raw[$key])) {
                $list = $raw[$key];
                break;
            }
        }
    }
    $transactions = [];
    foreach ($list as $t) {
        if (!is_array($t)) {
            continue;
        }
        $transactions[] = [
            'id' => $t['id'] ?? 0,
            'bcard_id' => $t['bcard_id'] ?? 0,
            'datetime' => $t['datetime'] ?? '',
            'increase' => $t['increase'] ?? 0,
            'decrease' => $t['decrease'] ?? 0,
            'amount' => $t['amount'] ?? 0,
            'amount_till_date' => $t['amount_till_date'] ?? null,
            'reason' => $t['reason'] ?? null,
            'manager_id' => $t['manager_id'] ?? null,
            'request_id' => $t['request_id'] ?? null,
        ];
    }
    return ['success' => true, 'data' => $transactions];
}

/**
 * @param array<int, array<string, mixed>> $transactions
 */
function crm_compute_bonus_balance(array $transactions): int
{
    $balance = 0;
    foreach ($transactions as $t) {
        if (($t['increase'] ?? 0) === 1) {
            $balance += (int) ($t['amount'] ?? 0);
        }
        if (($t['decrease'] ?? 0) === 1) {
            $balance -= (int) ($t['amount'] ?? 0);
        }
    }
    return $balance;
}

function crm_maybe_cors(array $config): void
{
    if (!empty($config['allow_cors'])) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
}
