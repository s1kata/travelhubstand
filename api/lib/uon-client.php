<?php
/**
 * Минимальный клиент U-ON API (ключ только на сервере).
 */
declare(strict_types=1);

/**
 * @return array{success: bool, data?: mixed, error?: string}
 */
function uon_request(string $endpoint, array $config, array $options = []): array
{
    $key = trim((string) ($config['uon_api_key'] ?? getenv('UON_API_KEY') ?: getenv('SOTA_API_KEY') ?: ''));
    if ($key === '') {
        return ['success' => false, 'error' => 'UON_API_KEY is not configured'];
    }

    $path = ltrim($endpoint, '/');
    $url = 'https://api.u-on.ru/' . $key . '/' . $path;
    $method = strtoupper((string) ($options['method'] ?? 'GET'));
    $body = $options['body'] ?? null;

    $ch = curl_init($url);
    if ($ch === false) {
        return ['success' => false, 'error' => 'curl init failed'];
    }

    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    if ($body !== null && $method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'error' => $curlErr ?: 'Network error'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = ['raw' => $raw];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = (string) ($data['message'] ?? $data['error'] ?? "HTTP {$httpCode}");
        return ['success' => false, 'error' => $msg];
    }

    return ['success' => true, 'data' => $data];
}
