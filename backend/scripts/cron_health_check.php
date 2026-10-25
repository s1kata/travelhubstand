<?php
/**
 * Проверка свежести cron-логов и lock-файлов (CLI на SpaceWeb).
 *
 * php backend/scripts/cron_health_check.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

/** @var array<string, int> $expectHours — макс. возраст лога, ч */
$checks = [
    'data/promo_warm.log' => 14,
    'data/home_search_warm.log' => 10,
    'data/calendar_warm.log' => 12,
    'data/yandex_yml_rules_cron.log' => 26,
    'data/cover_cleanup.log' => 48,
    'data/image_cache_cron.log' => 48,
    'data/tourvisor_cache_cleanup.log' => 192,
];

$now = time();
$bad = 0;

foreach ($checks as $rel => $maxHours) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        echo "[MISS] $rel — файла нет\n";
        $bad++;
        continue;
    }
    $ageH = ($now - (int) filemtime($path)) / 3600;
    $ok = $ageH <= (float) $maxHours;
    echo ($ok ? '[ OK ]' : '[OLD ]') . ' ' . $rel . ' — ' . round($ageH, 1) . " ч назад\n";
    if (!$ok) {
        $bad++;
    }
}

foreach (['data/promo_warm.lock', 'data/search_warm.lock', 'data/calendar_warm.lock'] as $lockRel) {
    $lockPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $lockRel);
    if (is_file($lockPath)) {
        $ageMin = ($now - (int) filemtime($lockPath)) / 60;
        if ($ageMin > 180) {
            echo "[WARN] $lockRel — lock старше 3 ч (возможно зависший прогон)\n";
            $bad++;
        }
    }
}

exit($bad > 0 ? 1 : 0);
