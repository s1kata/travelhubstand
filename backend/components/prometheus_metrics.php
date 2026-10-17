<?php
declare(strict_types=1);

function th_metrics_bucket_bounds(): array
{
    return [0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0, 120.0, 180.0];
}

function th_metrics_timestamp_ns(): string
{
    $now = microtime(true);
    $seconds = (int) floor($now);
    $nanos = (int) (($now - $seconds) * 1000000000);

    return (string) ($seconds * 1000000000 + $nanos);
}

function th_metrics_log(string $message): void
{
    $root = dirname(__DIR__, 2);
    $logDir = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $line = '[' . gmdate('Y-m-d\TH:i:s\Z') . '] ' . $message . PHP_EOL;
    if (is_dir($logDir) && is_writable($logDir)) {
        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'prometheus_metrics.log', $line, FILE_APPEND | LOCK_EX);
        return;
    }

    error_log('[metrics] ' . $message);
}

function th_metrics_observe(
    string $metricName,
    string $operation,
    string $source,
    string $result,
    float $durationSeconds
): bool {
    static $allowedMetrics = [
        'travelhub_request_duration_seconds',
        'travelhub_upstream_attempt_duration_seconds',
    ];
    static $allowedOperations = [
        'search_start',
        'search_poll',
        'search_results',
        'search_cache',
        'promotions',
        'tourvisor_search_http',
        'tourvisor_status_http',
        'tourvisor_results_http',
        'tourvisor_promo_http',
        'tourvisor_other_http',
    ];

    if (!in_array($metricName, $allowedMetrics, true)
        || !in_array($operation, $allowedOperations, true)
        || !in_array($source, ['cache', 'live', 'unknown'], true)
        || !in_array($result, ['success', 'error'], true)
        || !is_finite($durationSeconds)
        || $durationSeconds < 0) {
        th_metrics_log('Rejected invalid observation labels or duration.');
        return false;
    }

    global $pdo;
    if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        th_metrics_log('MySQL connection is unavailable; metric observation was not stored.');
        return false;
    }

    $bucketIndex = count(th_metrics_bucket_bounds());
    foreach (th_metrics_bucket_bounds() as $index => $bound) {
        if ($durationSeconds <= $bound) {
            $bucketIndex = $index;
            break;
        }
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO th_prometheus_histogram_buckets
                (metric_name, operation, source, result, bucket_index, sample_count, duration_sum, first_seen_ns)
             VALUES
                (:metric_name, :operation, :source, :result, :bucket_index, 1, :duration_sum, :first_seen_ns)
             ON DUPLICATE KEY UPDATE
                sample_count = sample_count + 1,
                duration_sum = duration_sum + VALUES(duration_sum)'
        );
        $stmt->execute([
            ':metric_name' => $metricName,
            ':operation' => $operation,
            ':source' => $source,
            ':result' => $result,
            ':bucket_index' => $bucketIndex,
            ':duration_sum' => $durationSeconds,
            ':first_seen_ns' => th_metrics_timestamp_ns(),
        ]);

        return true;
    } catch (Throwable $e) {
        th_metrics_log('Metric write failed: ' . $e->getMessage());
        return false;
    }
}

function th_metrics_otlp_payload(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT metric_name, operation, source, result, bucket_index,
                SUM(sample_count) AS sample_count,
                SUM(duration_sum) AS duration_sum,
                MIN(first_seen_ns) AS first_seen_ns
         FROM th_prometheus_histogram_buckets
         GROUP BY metric_name, operation, source, result, bucket_index
         ORDER BY metric_name, operation, source, result, bucket_index'
    );

    $bounds = th_metrics_bucket_bounds();
    $series = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = implode('|', [
            $row['metric_name'],
            $row['operation'],
            $row['source'],
            $row['result'],
        ]);
        if (!isset($series[$key])) {
            $series[$key] = [
                'metric_name' => (string) $row['metric_name'],
                'operation' => (string) $row['operation'],
                'source' => (string) $row['source'],
                'result' => (string) $row['result'],
                'bucket_counts' => array_fill(0, count($bounds) + 1, 0),
                'count' => 0,
                'sum' => 0.0,
                'start_time_unix_nano' => (string) $row['first_seen_ns'],
            ];
        }

        $bucketIndex = (int) $row['bucket_index'];
        if (isset($series[$key]['bucket_counts'][$bucketIndex])) {
            $series[$key]['bucket_counts'][$bucketIndex] = (int) $row['sample_count'];
        }
        $series[$key]['count'] += (int) $row['sample_count'];
        $series[$key]['sum'] += (float) $row['duration_sum'];
        if (strcmp((string) $row['first_seen_ns'], (string) $series[$key]['start_time_unix_nano']) < 0) {
            $series[$key]['start_time_unix_nano'] = (string) $row['first_seen_ns'];
        }
    }

    $groupedMetrics = [];
    foreach ($series as $item) {
        $cumulative = 0;
        $bucketCounts = [];
        foreach ($item['bucket_counts'] as $count) {
            $cumulative += $count;
            $bucketCounts[] = (string) $cumulative;
        }

        $groupedMetrics[$item['metric_name']][] = [
            'attributes' => [
                ['key' => 'operation', 'value' => ['stringValue' => $item['operation']]],
                ['key' => 'source', 'value' => ['stringValue' => $item['source']]],
                ['key' => 'result', 'value' => ['stringValue' => $item['result']]],
            ],
            'startTimeUnixNano' => $item['start_time_unix_nano'],
            'timeUnixNano' => th_metrics_timestamp_ns(),
            'count' => (string) $item['count'],
            'sum' => $item['sum'],
            'bucketCounts' => $bucketCounts,
            'explicitBounds' => $bounds,
        ];
    }

    $metrics = [];
    foreach ($groupedMetrics as $name => $dataPoints) {
        $metrics[] = [
            'name' => $name,
            'description' => 'Cumulative request duration histogram recorded by TravelHub.',
            'unit' => 's',
            'histogram' => [
                'aggregationTemporality' => 2,
                'dataPoints' => $dataPoints,
            ],
        ];
    }

    if ($metrics === []) {
        return [];
    }

    $resourceAttributes = [
        ['key' => 'service.name', 'value' => ['stringValue' => 'travelhub-v2']],
    ];
    $environment = trim((string) (getenv('DEPLOYMENT_ENVIRONMENT') ?: ($_ENV['DEPLOYMENT_ENVIRONMENT'] ?? '')));
    if ($environment !== '') {
        $resourceAttributes[] = ['key' => 'deployment.environment', 'value' => ['stringValue' => $environment]];
    }

    return [
        'resourceMetrics' => [[
            'resource' => ['attributes' => $resourceAttributes],
            'scopeMetrics' => [[
                'scope' => ['name' => 'travelhub.metrics', 'version' => '1.0.0'],
                'metrics' => $metrics,
            ]],
        ]],
    ];
}
