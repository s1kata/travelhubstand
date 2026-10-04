<?php
/**
 * Файловый кэш акционных туров по стране: backend/cache/promo_cache_{countryId}.json
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/departure_defaults.php';

function th_promo_cache_dir(): string
{
    $root = $GLOBALS['tv_project_root'] ?? (function_exists('th_project_root') ? th_project_root() : dirname(__DIR__, 2));
    $dir = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function th_promo_cache_file(int $countryId): string
{
    return th_promo_cache_dir() . DIRECTORY_SEPARATOR . 'promo_cache_' . $countryId . '.json';
}

function th_promo_cache_ttl_seconds(): int
{
    $h = (float) (getenv('PROMO_COUNTRY_CACHE_TTL_HOURS') ?: ($_ENV['PROMO_COUNTRY_CACHE_TTL_HOURS'] ?? 12));
    $h = min(48, max(12, $h));
    return (int) ($h * 3600);
}

/**
 * @return array{results: array, departureId?: int, dateFrom?: string, dateTo?: string}|null
 */
function th_promo_cache_get(int $countryId, ?int $departureId = null): ?array
{
    if ($countryId <= 0) {
        return null;
    }
    $file = th_promo_cache_file($countryId);
    if (!is_file($file)) {
        return null;
    }
    $age = time() - (int) filemtime($file);
    if ($age >= th_promo_cache_ttl_seconds()) {
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return null;
    }
    $d = json_decode($raw, true);
    if (!is_array($d) || !isset($d['results']) || !is_array($d['results'])) {
        return null;
    }
    if ($departureId !== null && $departureId > 0) {
        $cachedDep = (int) ($d['departureId'] ?? 0);
        if ($cachedDep > 0 && $cachedDep !== $departureId) {
            return null;
        }
    }
    return $d;
}

/** @param array<int, mixed> $results */
function th_promo_cache_set(int $countryId, array $results, array $meta = []): void
{
    if ($countryId <= 0) {
        return;
    }
    $payload = [
        'results' => $results,
        'cachedAt' => time(),
        'departureId' => (int) ($meta['departureId'] ?? th_departure_default_id()),
        'dateFrom' => (string) ($meta['dateFrom'] ?? ''),
        'dateTo' => (string) ($meta['dateTo'] ?? ''),
    ];
    @file_put_contents(
        th_promo_cache_file($countryId),
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}
