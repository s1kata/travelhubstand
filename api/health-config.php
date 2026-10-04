<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configFile = __DIR__ . '/auth-mobile.config.php';
$cfg = is_file($configFile) ? require $configFile : [];

$val = static function(array $cfg, string $cfgKey, string $envKey): string {
    $fromCfg = $cfg[$cfgKey] ?? '';
    if (is_string($fromCfg) && trim($fromCfg) !== '') return trim($fromCfg);
    $fromEnv = getenv($envKey);
    return is_string($fromEnv) ? trim($fromEnv) : '';
};

$jwt = $val($cfg, 'jwt_secret', 'JWT_SECRET');
$tk = $val($cfg, 'tinkoff_terminal_key', 'TINKOFF_TERMINAL_KEY');
$tp = $val($cfg, 'tinkoff_password', 'TINKOFF_PASSWORD');

echo json_encode([
    'success' => true,
    'checks' => [
        'auth_mobile_config_exists' => is_file($configFile),
        'jwt_secret_set' => $jwt !== '',
        'tinkoff_terminal_key_set' => $tk !== '',
        'tinkoff_password_set' => $tp !== '',
    ],
    // Длины безопасно показывать, сами значения не выводим
    'lengths' => [
        'jwt_secret' => strlen($jwt),
        'tinkoff_terminal_key' => strlen($tk),
        'tinkoff_password' => strlen($tp),
    ],
], JSON_UNESCAPED_UNICODE);