<?php
/**
 * Tinkoff T-Kassa: подпись запросов (Token) и проверка подписи уведомлений.
 * Секреты только из переменных окружения (TINKOFF_TERMINAL_KEY, TINKOFF_PASSWORD).
 */
declare(strict_types=1);

/**
 * Формирует подпись Token для запроса к Tinkoff API.
 * Все параметры (включая Password) сортируются по ключу, значения конкатенируются, SHA-256.
 *
 * @param array<string, mixed> $params Параметры запроса (без Token). Значения приводятся к строке.
 * @param string $password Пароль терминала (из env).
 * @return string Хеш SHA-256 в нижнем регистре.
 */
function tinkoff_sign_request(array $params, string $password): string
{
    $params['Password'] = $password;
    ksort($params);
    $concatenated = '';
    foreach ($params as $value) {
        $concatenated .= (string) $value;
    }
    return hash('sha256', $concatenated);
}

/**
 * Проверяет подпись уведомления от Tinkoff (NotificationURL).
 * В массив для подписи не входят: Token, вложенные объекты (Data, Receipt и т.д.).
 *
 * @param array<string, mixed> $body Тело POST-запроса (все поля, которые прислал Tinkoff).
 * @param string $password Пароль терминала.
 * @return bool true если переданный Token совпадает с вычисленной подписью.
 */
function tinkoff_verify_notification_token(array $body, string $password): bool
{
    $receivedToken = $body['Token'] ?? '';
    if ($receivedToken === '') {
        return false;
    }

    $excludeKeys = ['Token', 'Data', 'Receipt'];
    $params = [];
    foreach ($body as $key => $value) {
        if (in_array($key, $excludeKeys, true)) {
            continue;
        }
        if (is_array($value) || is_object($value)) {
            continue;
        }
        $params[$key] = (string) $value;
    }
    $params['Password'] = $password;
    ksort($params);
    $concatenated = '';
    foreach ($params as $value) {
        $concatenated .= $value;
    }
    $expectedToken = hash('sha256', $concatenated);
    return hash_equals($expectedToken, $receivedToken);
}

/**
 * Возвращает TerminalKey из env. Пустая строка, если не задан.
 */
function tinkoff_get_terminal_key(): string
{
    return trim((string) (getenv('TINKOFF_TERMINAL_KEY') ?: ($_ENV['TINKOFF_TERMINAL_KEY'] ?? '')));
}

/**
 * Возвращает пароль терминала из env. Пустая строка, если не задан.
 */
function tinkoff_get_password(): string
{
    return trim((string) (getenv('TINKOFF_PASSWORD') ?: ($_ENV['TINKOFF_PASSWORD'] ?? '')));
}

/**
 * Базовый URL сайта для SuccessURL, FailURL, NotificationURL (из APP_URL или API_URL).
 */
function tinkoff_get_base_url(): string
{
    $url = trim((string) (getenv('API_URL') ?: getenv('APP_URL') ?: ($_ENV['API_URL'] ?? $_ENV['APP_URL'] ?? '')));
    if ($url === '') {
        $url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return rtrim($url, '/');
}
