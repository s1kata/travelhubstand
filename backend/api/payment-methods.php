<?php
/**
 * Список доступных способов оплаты для выбора после бронирования.
 * Включённые методы определяются по наличию ключей в .env (лотки под ключи).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Т-касса (Tinkoff): тот же терминал, что mobile API /api/create-payment
$tinkoff_terminal = getenv('TINKOFF_TERMINAL_KEY') ?: ($_ENV['TINKOFF_TERMINAL_KEY'] ?? '');
$tinkoff_password = getenv('TINKOFF_PASSWORD') ?: ($_ENV['TINKOFF_PASSWORD'] ?? '');

$methods = [
    [
        'id' => 'tbank',
        'name' => 'Т-Банк',
        'enabled' => ($tinkoff_terminal !== '' && $tinkoff_password !== ''),
    ],
];

echo json_encode(['methods' => $methods]);
