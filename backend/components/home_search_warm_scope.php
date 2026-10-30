<?php
/**
 * Фильтрация списка стран для cover-warm поиска (поэтапный ночной прогон).
 */
declare(strict_types=1);

/**
 * Загружает popular_countries.php или fallback.
 *
 * @return list<array{id: int, name: string}>
 */
function th_home_search_warm_load_popular(): array
{
    $popularFile = dirname(__DIR__) . '/config/popular_countries.php';
    $popular = is_file($popularFile) ? require $popularFile : [];
    if (!is_array($popular) || $popular === []) {
        return [['id' => 4, 'name' => 'Турция']];
    }

    return $popular;
}

/**
 * Читает scope/phase из env и argv (--phase=0, --scope=nightly).
 *
 * @param list<string> $argv
 * @return array{scope: string, phase: int|null, phaseCount: int}
 */
function th_home_search_warm_resolve_scope(array $argv = []): array
{
    $scope = strtolower(trim((string) (getenv('TH_WARM_SCOPE') ?: ($_ENV['TH_WARM_SCOPE'] ?? 'all'))));
    if ($scope !== 'nightly') {
        $scope = 'all';
    }

    $phaseCount = (int) (getenv('TH_WARM_PHASE_COUNT') ?: ($_ENV['TH_WARM_PHASE_COUNT'] ?? 3));
    $phaseCount = max(1, min(6, $phaseCount));

    $phaseRaw = getenv('TH_WARM_PHASE') ?: ($_ENV['TH_WARM_PHASE'] ?? null);
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--scope=')) {
            $v = strtolower(trim(substr($arg, 8)));
            $scope = ($v === 'nightly') ? 'nightly' : 'all';
        }
        if (str_starts_with($arg, '--phase=')) {
            $phaseRaw = trim(substr($arg, 8));
        }
    }

    $phase = null;
    if ($scope === 'nightly') {
        if ($phaseRaw === null || $phaseRaw === '' || strtolower((string) $phaseRaw) === 'auto') {
            $phase = ((int) date('z')) % $phaseCount;
        } else {
            $phase = (int) $phaseRaw;
            $phase = (($phase % $phaseCount) + $phaseCount) % $phaseCount;
        }
    }

    return [
        'scope' => $scope,
        'phase' => $phase,
        'phaseCount' => $phaseCount,
    ];
}

/**
 * Оставляет страны очередной ночной фазы (round-robin по индексу в popular_countries).
 *
 * @param list<array{id: int, name: string}> $popular
 * @return list<array{id: int, name: string}>
 */
function th_home_search_warm_filter_phase(array $popular, int $phase, int $phaseCount): array
{
    $out = [];
    foreach ($popular as $i => $row) {
        if ($i % $phaseCount === $phase) {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * @param list<array{id: int, name: string}> $popular
 * @param array{scope: string, phase: int|null, phaseCount: int} $scopeMeta
 * @return array{popular: list<array{id: int, name: string}>, scope: string, phase: int|null, phaseCount: int}
 */
function th_home_search_warm_apply_scope(array $popular, array $scopeMeta): array
{
    $scope = $scopeMeta['scope'];
    $phase = $scopeMeta['phase'];
    $phaseCount = $scopeMeta['phaseCount'];

    if ($scope === 'nightly' && $phase !== null) {
        $popular = th_home_search_warm_filter_phase($popular, $phase, $phaseCount);
    }

    return [
        'popular' => $popular,
        'scope' => $scope,
        'phase' => $phase,
        'phaseCount' => $phaseCount,
    ];
}
