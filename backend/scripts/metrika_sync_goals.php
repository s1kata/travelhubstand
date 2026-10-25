<?php
/**
 * Создаёт в Яндекс.Метрике JS-цели из backend/config/metrika_goals_catalog.php.
 *
 * Токен: YANDEX_METRIKA_OAUTH_TOKEN (OAuth, scope metrika:write).
 * Счётчик: YANDEX_METRIKA_ID или 113554694.
 *
 *   php backend/scripts/metrika_sync_goals.php --dry-run
 *   YANDEX_METRIKA_OAUTH_TOKEN=y0_... php backend/scripts/metrika_sync_goals.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$catalogPath = $root . '/backend/config/metrika_goals_catalog.php';
if (!is_file($catalogPath)) {
    fwrite(STDERR, "Catalog not found\n");
    exit(1);
}

/** @var array<int, array{id: string, name: string, funnel?: string, key?: bool}> $catalog */
$catalog = require $catalogPath;

$dryRun = in_array('--dry-run', $argv, true);
$counterId = preg_replace('/\D/', '', (string) (getenv('YANDEX_METRIKA_ID') ?: ($_ENV['YANDEX_METRIKA_ID'] ?? '113554694'))) ?: '113554694';
$token = trim((string) (getenv('YANDEX_METRIKA_OAUTH_TOKEN') ?: ($_ENV['YANDEX_METRIKA_OAUTH_TOKEN'] ?? '')));

$existingIds = [];
if ($token !== '') {
    $existingIds = metrika_fetch_existing_goal_identifiers($counterId, $token);
}

$toCreate = [];
foreach ($catalog as $row) {
    $id = trim((string) ($row['id'] ?? ''));
    if ($id === '') {
        continue;
    }
    if (isset($existingIds[$id])) {
        continue;
    }
    $toCreate[] = $row;
}

echo 'Counter: ' . $counterId . PHP_EOL;
echo 'Catalog goals: ' . count($catalog) . PHP_EOL;
echo 'Already in Metrika: ' . count($existingIds) . PHP_EOL;
echo 'To create: ' . count($toCreate) . PHP_EOL;

if ($dryRun || $token === '') {
    if ($token === '') {
        echo PHP_EOL . 'Set YANDEX_METRIKA_OAUTH_TOKEN to create goals (dry-run only).' . PHP_EOL;
    }
    foreach ($toCreate as $row) {
        $mark = !empty($row['key']) ? ' [KEY]' : '';
        echo '  + ' . $row['id'] . ' — ' . $row['name'] . $mark . PHP_EOL;
    }
    exit(0);
}

$created = 0;
$failed = 0;
foreach ($toCreate as $row) {
    $id = (string) $row['id'];
    $name = (string) ($row['name'] ?? $id);
    $ok = metrika_create_js_goal($counterId, $token, $id, $name);
    if ($ok) {
        $created++;
        echo 'Created: ' . $id . PHP_EOL;
    } else {
        $failed++;
        echo 'FAILED: ' . $id . PHP_EOL;
    }
    usleep(200000);
}

echo PHP_EOL . "Done. Created={$created}, failed={$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);

/**
 * @return array<string, true>
 */
function metrika_fetch_existing_goal_identifiers(string $counterId, string $token): array
{
    $out = [];
    $offset = 1;
    $perPage = 100;
    do {
        $url = 'https://api-metrika.yandex.net/management/v1/counter/' . rawurlencode($counterId)
            . '/goals?per_page=' . $perPage . '&offset=' . $offset;
        $json = metrika_http_get_json($url, $token);
        if (!$json || !isset($json['goals']) || !is_array($json['goals'])) {
            break;
        }
        foreach ($json['goals'] as $goal) {
            if (!is_array($goal)) {
                continue;
            }
            $conditions = $goal['conditions'] ?? [];
            if (!is_array($conditions)) {
                continue;
            }
            foreach ($conditions as $cond) {
                if (!is_array($cond)) {
                    continue;
                }
                $urlId = trim((string) ($cond['url'] ?? ''));
                if ($urlId !== '') {
                    $out[$urlId] = true;
                }
            }
        }
        $total = (int) ($json['goals_total'] ?? count($json['goals']));
        $offset += $perPage;
    } while ($offset <= $total + $perPage);

    return $out;
}

function metrika_create_js_goal(string $counterId, string $token, string $identifier, string $name): bool
{
    $payload = [
        'goal' => [
            'name' => $name,
            'type' => 'action',
            'is_retargeting' => 0,
            'conditions' => [
                [
                    'type' => 'action',
                    'url' => $identifier,
                ],
            ],
        ],
    ];
    $url = 'https://api-metrika.yandex.net/management/v1/counter/' . rawurlencode($counterId) . '/goals';
    $json = metrika_http_post_json($url, $token, $payload);
    return is_array($json) && isset($json['goal']);
}

/**
 * @return array<string, mixed>|null
 */
function metrika_http_get_json(string $url, string $token): ?array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: OAuth ' . $token],
        CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!is_string($body) || $body === '') {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>|null
 */
function metrika_http_post_json(string $url, string $token, array $payload): ?array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_HTTPHEADER => [
            'Authorization: OAuth ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!is_string($body) || $body === '') {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}
