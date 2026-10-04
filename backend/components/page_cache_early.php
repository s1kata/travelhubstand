<?php
/**
 * Ранняя проверка кэша страницы — без подключения config/БД.
 * Подключайте в самом начале страницы; затем вызовите PageCache::get() и exit при HIT.
 *
 * Использование (например, страницы стран):
 *   require_once __DIR__ . '/../../../backend/components/page_cache_early.php';
 *   if (PageCache::get()) exit;
 *   require_once __DIR__ . '/../../../backend/config/config.php';
 *   ...
 */
require_once __DIR__ . '/page_cache.php';
if (isset($_COOKIE[session_name()]) && $_COOKIE[session_name()] !== '') {
    session_start();
}
