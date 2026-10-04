<?php
/**
 * Подключение Tailwind CSS (собранный purged build, не CDN).
 */
if (!defined('TH_TAILWIND_LOADED')) {
    define('TH_TAILWIND_LOADED', true);
    $tailwind_built = dirname(__DIR__, 2) . '/frontend/css/tailwind.min.css';
    if (file_exists($tailwind_built)) {
        echo '<link rel="stylesheet" href="/frontend/css/tailwind.min.css?v=1">' . "\n";
    }
}
?>
