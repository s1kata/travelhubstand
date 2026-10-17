<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/prometheus_metrics.php';

function metrics_export_fail(string $message): void
{
    fwrite(STDERR, '[metrics-export] ' . $message . PHP_EOL);
    exit(1);
}

global $pdo;
if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    metrics_export_fail('Expected an active MySQL PDO connection. Check DB_DRIVER and database settings.');
}

$root = dirname(__DIR__, 2);
$initRequested = in_array('--init', $argv, true);
$lockHandle = null;

if ($initRequested) {
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS th_prometheus_histogram_buckets (
                metric_name VARCHAR(64) NOT NULL,
                operation VARCHAR(40) NOT NULL,
                source VARCHAR(12) NOT NULL,
                result VARCHAR(8) NOT NULL,
                bucket_index TINYINT UNSIGNED NOT NULL,
                sample_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                duration_sum DOUBLE NOT NULL DEFAULT 0,
                first_seen_ns BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (metric_name, operation, source, result, bucket_index)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        fwrite(STDOUT, "Metrics table is ready.\n");
        exit(0);
    } catch (Throwable $e) {
        metrics_export_fail('Could not create metrics table: ' . $e->getMessage());
    }
}

$lockPath = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'metrics_export.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false) {
    metrics_export_fail('Cannot open the metrics export lock file.');
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "Another export is still running; skipped.\n");
    exit(0);
}

$endpoint = trim((string) (getenv('GRAFANA_OTLP_METRICS_ENDPOINT') ?: ($_ENV['GRAFANA_OTLP_METRICS_ENDPOINT'] ?? '')));
$instanceId = trim((string) (getenv('GRAFANA_CLOUD_INSTANCE_ID') ?: ($_ENV['GRAFANA_CLOUD_INSTANCE_ID'] ?? '')));
$apiToken = trim((string) (getenv('GRAFANA_CLOUD_API_TOKEN') ?: ($_ENV['GRAFANA_CLOUD_API_TOKEN'] ?? '')));
if ($endpoint === '' || $instanceId === '' || $apiToken === '') {
    metrics_export_fail('Set GRAFANA_OTLP_METRICS_ENDPOINT, GRAFANA_CLOUD_INSTANCE_ID and GRAFANA_CLOUD_API_TOKEN in .env.');
}

$endpointParts = parse_url($endpoint);
if (!is_array($endpointParts) || strtolower((string) ($endpointParts['scheme'] ?? '')) !== 'https') {
    metrics_export_fail('The OTLP metrics endpoint must be a valid HTTPS URL.');
}
if (!function_exists('curl_init')) {
    metrics_export_fail('The PHP cURL extension is required to export metrics.');
}

try {
    $payload = th_metrics_otlp_payload($pdo);
} catch (Throwable $e) {
    metrics_export_fail('Could not read accumulated metrics: ' . $e->getMessage());
}

if ($payload === []) {
    fwrite(STDOUT, "No observations yet; nothing to export.\n");
    exit(0);
}

$body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_string($body)) {
    metrics_export_fail('Could not encode the OTLP metrics payload.');
}

$ch = curl_init($endpoint);
if ($ch === false) {
    metrics_export_fail('Could not initialize cURL.');
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($instanceId . ':' . $apiToken),
    ],
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    metrics_export_fail('OTLP request failed: ' . $curlError);
}
if ($httpStatus < 200 || $httpStatus >= 300) {
    metrics_export_fail('Grafana Cloud returned HTTP ' . $httpStatus . '.');
}

$pointCount = 0;
foreach ($payload['resourceMetrics'] as $resourceMetrics) {
    foreach ($resourceMetrics['scopeMetrics'] as $scopeMetrics) {
        foreach ($scopeMetrics['metrics'] as $metric) {
            $pointCount += count($metric['histogram']['dataPoints']);
        }
    }
}
fwrite(STDOUT, 'Exported ' . $pointCount . ' cumulative histogram series; HTTP ' . $httpStatus . ".\n");
