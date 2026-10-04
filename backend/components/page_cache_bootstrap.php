<?php
/**
 * Bootstrap для кэширования страниц — добавляйте в начало публичных страниц.
 * Вызовет session_start, проверит кэш и начнёт буферизацию.
 *
 * Использование:
 *   require_once __DIR__ . '/path/to/config.php';
 *   require_once __DIR__ . '/path/to/page_cache_bootstrap.php';
 *   // ... остальной код страницы ...
 *   PageCache::end(); // в конце, перед </body> или в футере
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/page_cache.php';

// TTL можно задать перед require: define('PAGE_CACHE_TTL', 3600);
if (PageCache::get()) {
    exit;
}
PageCache::start();
