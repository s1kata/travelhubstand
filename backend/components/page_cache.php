<?php
/**
 * Система кэширования HTML страниц для решения проблемы TTFB
 * Кэширует готовый HTML, чтобы сервер не генерировал его каждый раз
 */

class PageCache {
    private static $cacheDir = null;
    private static $enabled = true;
    private static $ttl = 900; // 15 минут по умолчанию: меньше «рассинхрона» у пользователей
    public static $debugStatus = ['page' => 'MISS', 'reason' => 'generating'];
    
    /**
     * Инициализация кэша
     */
    public static function init($ttl = 3600, $enabled = true) {
        self::$ttl = $ttl;
        self::$enabled = $enabled && !self::isAdminRequest();
        
        if (self::$enabled) {
            $baseDir = dirname(__DIR__, 2);
            self::$cacheDir = $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'page_cache';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0755, true);
            }
        }
    }
    
    /**
     * Проверка, является ли запрос админским или от авторизованного пользователя
     */
    private static function isAdminRequest() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/backend/admin/') !== false || strpos($uri, '/admin') !== false || isset($_GET['nocache'])) {
            return true;
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
            return true;
        }
        return false;
    }
    
    /**
     * Генерация ключа кэша на основе URL и параметров
     */
    private static function getCacheKey() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $query = $_SERVER['QUERY_STRING'] ?? '';
        $lang = $_GET['lang'] ?? 'ru';
        
        // Исключаем параметры, которые не влияют на контент
        $query = preg_replace('/&?nocache=\d+/', '', $query);
        $query = preg_replace('/&?_=\d+/', '', $query);
        
        $assetVer = '1';
        if (!defined('TH_ASSET_VERSION')) {
            $avFile = dirname(__DIR__) . '/config/asset_version.php';
            if (is_file($avFile)) {
                require_once $avFile;
            }
        }
        if (defined('TH_ASSET_VERSION')) {
            $assetVer = TH_ASSET_VERSION;
        }
        
        $key = md5($uri . '|' . $query . '|' . $lang . '|' . $assetVer);
        return self::$cacheDir . DIRECTORY_SEPARATOR . $key . '.html';
    }
    
    /**
     * Получить кэшированную страницу
     */
    public static function get() {
        if (!self::$enabled) {
            return false;
        }

        // Главная с формой поиска Tourvisor — не отдаём из кэша, чтобы всегда был актуальный JS и корректные результаты
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/index.php') !== false || preg_match('#^/?$#', trim(parse_url($uri, PHP_URL_PATH) ?: '/'))) {
            return false;
        }

        // Офисы и список стран часто меняются вручную (фото/команда/порядок) — не отдаём из HTML-кэша
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '');
        if (strpos($path, '/frontend/window/offices') === 0 || $path === '/frontend/window/countries-list.php') {
            return false;
        }
        
        // Не отдаём кэш авторизованным пользователям (персонализированный контент)
        if (self::isAdminRequest()) {
            return false;
        }
        
        $cacheFile = self::getCacheKey();
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $mtime = filemtime($cacheFile);
        $age = time() - $mtime;
        if ($age > self::$ttl) {
            @unlink($cacheFile);
            return false;
        }

        $size = filesize($cacheFile);
        $etag = '"' . md5((string)$mtime . '-' . (string)$size) . '"';
        $lastMod = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

        header('X-Cache: HIT');
        header('X-Cache-Age: ' . $age);
        header('X-Cache-TTL: ' . self::$ttl);
        header('Cache-Control: public, max-age=' . max(60, self::$ttl - $age));
        header('Last-Modified: ' . $lastMod);
        header('ETag: ' . $etag);

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if (trim($ifNoneMatch) === $etag || ($ifModifiedSince && strtotime($ifModifiedSince) >= $mtime)) {
            header('HTTP/1.1 304 Not Modified');
            return true;
        }

        $content = file_get_contents($cacheFile);
        $content = preg_replace('/<script>window\.__CACHE_DEBUG[^<]*<\/script>\s*/', '', $content);
        $content = preg_replace('/<script[^>]*src="[^"]*cache-debug\.js"[^>]*><\/script>\s*/i', '', $content);

        header('Content-Length: ' . strlen($content));
        echo $content;
        return true;
    }
    
    /**
     * Сохранить страницу в кэш
     */
    public static function save($content) {
        if (!self::$enabled) {
            return false;
        }
        
        $cacheFile = self::getCacheKey();
        
        // Сохраняем в фоне, чтобы не блокировать ответ
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        file_put_contents($cacheFile, $content, LOCK_EX);
        
        // Устанавливаем права доступа
        @chmod($cacheFile, 0644);
        
        return true;
    }
    
    /**
     * Очистить весь кэш
     */
    public static function clear() {
        if (!self::$cacheDir || !is_dir(self::$cacheDir)) {
            return false;
        }
        
        $files = glob(self::$cacheDir . DIRECTORY_SEPARATOR . '*.html');
        foreach ($files as $file) {
            @unlink($file);
        }
        
        return true;
    }
    
    /**
     * Очистить кэш для конкретной страницы
     */
    public static function clearPage($uri) {
        if (!self::$cacheDir || !is_dir(self::$cacheDir)) {
            return false;
        }
        
        // Находим все файлы кэша и проверяем их содержимое
        $files = glob(self::$cacheDir . DIRECTORY_SEPARATOR . '*.html');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, $uri) !== false) {
                @unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Начать буферизацию вывода
     */
    public static function start() {
        if (self::$enabled) {
            header('X-Cache: MISS');
            header('X-Cache-Reason: generating');
            self::$debugStatus = ['page' => 'MISS', 'reason' => 'generating'];
            if (self::$cacheDir && is_dir(self::$cacheDir)) {
                self::$debugStatus['pageCacheFiles'] = count(glob(self::$cacheDir . '/*.html'));
            }
            ob_start();
        } else {
            header('X-Cache: BYPASS');
            header('X-Cache-Reason: disabled-or-admin');
            self::$debugStatus = ['page' => 'BYPASS', 'reason' => 'disabled-or-admin'];
        }
    }
    
    /**
     * Завершить буферизацию и сохранить в кэш
     */
    public static function end() {
        if (self::$enabled && ob_get_level() > 0) {
            $content = ob_get_contents();
            ob_end_flush();
            // Не сохраняем в кэш главную страницу (форма поиска — всегда свежий HTML)
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/index.php') !== false || preg_match('#^/?$#', trim(parse_url($uri, PHP_URL_PATH) ?: '/'))) {
                return;
            }
            self::save($content);
        }
    }
}

// Автоматическая инициализация (TTL: 2 ч по умолчанию — нормальная работа на сервере и домене)
if (!defined('PAGE_CACHE_DISABLED')) {
    $cacheTTL = defined('PAGE_CACHE_TTL') ? PAGE_CACHE_TTL : 7200;
    PageCache::init($cacheTTL);
}
