<?php
declare(strict_types=1);

/**
 * Публичная выдача YML-фида для Яндекс Бизнеса.
 * URL: /export/services_yml.php
 */

$projectRoot = dirname(__DIR__);
$ymlFile = $projectRoot . DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR . 'services.yml';
$generatorScript = $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'generate_yml.php';
$lockFile = $projectRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'yml_generation.lock';

// Обновляем файл не чаще, чем раз в 10 минут при входящих запросах.
$ttlSeconds = (int)(getenv('YML_TTL_SECONDS') ?: 600);
if ($ttlSeconds < 60) {
    $ttlSeconds = 60;
}

$needsRegenerate = !is_file($ymlFile) || ((time() - (int)@filemtime($ymlFile)) > $ttlSeconds);
if ($needsRegenerate) {
    $lockHandle = @fopen($lockFile, 'c+');
    if ($lockHandle !== false) {
        if (@flock($lockHandle, LOCK_EX)) {
            clearstatcache(true, $ymlFile);
            $needsRegenerate = !is_file($ymlFile) || ((time() - (int)@filemtime($ymlFile)) > $ttlSeconds);
            if ($needsRegenerate) {
                require_once $generatorScript;
                if (function_exists('generate_services_yml')) {
                    generate_services_yml($pdo ?? null);
                }
            }
            @flock($lockHandle, LOCK_UN);
        }
        @fclose($lockHandle);
    } else {
        // Fallback: если lock недоступен, всё равно пробуем сгенерировать.
        require_once $generatorScript;
        if (function_exists('generate_services_yml')) {
            generate_services_yml($pdo ?? null);
        }
    }
}

if (!is_file($ymlFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "YML file is not available.";
    exit;
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

readfile($ymlFile);
exit;

