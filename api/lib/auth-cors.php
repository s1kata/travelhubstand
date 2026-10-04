<?php
/**
 * CORS: whitelist origins only (no wildcard *).
 */
declare(strict_types=1);

function auth_apply_cors(array $config): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }

    $allowed = [];
    if (!empty($config['allowed_origins']) && is_array($config['allowed_origins'])) {
        $allowed = $config['allowed_origins'];
    } elseif (!empty($config['site_url'])) {
        $allowed = [rtrim((string) $config['site_url'], '/')];
    }

    $allowed = array_values(array_filter(array_map(static function ($o) {
        return rtrim(trim((string) $o), '/');
    }, $allowed)));

    $normalizedOrigin = rtrim($origin, '/');
    if (!in_array($normalizedOrigin, $allowed, true)) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Health-Token');
}
