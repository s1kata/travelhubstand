<?php
/**
 * Прокси для картинок Tourvisor (static.tourvisor.ru).
 * У static.tourvisor.ru нет HTTPS → ERR_SSL_PROTOCOL_ERROR при загрузке с HTTPS-сайта.
 * Сначала ищет файл локально (hotel_pics/…), затем кэш и удалённый static.tourvisor.ru.
 *
 * Использование: ?url=https://static.tourvisor.ru/hotel_pics/main400/123.jpg
 * или         ?path=hotel_pics/main400/123.jpg
 */
declare(strict_types=1);

// Диагностика (раскомментировать при отладке на сервере):
// ini_set('display_errors', '1');
// error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');

$allowedHost = 'static.tourvisor.ru';

/**
 * @return list<string>
 */
function tv_image_proxy_local_roots(): array
{
    $roots = [];
    $envRoot = trim((string) (
        getenv('TOURVISOR_HOTEL_PICS_ROOT')
        ?: ($_ENV['TOURVISOR_HOTEL_PICS_ROOT'] ?? '')
    ));
    if ($envRoot !== '') {
        $roots[] = rtrim(str_replace('\\', '/', $envRoot), '/');
    }

    $projectRoot = dirname(__DIR__, 2);
    $roots[] = rtrim(str_replace('\\', '/', $projectRoot), '/');

    $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/')
        : '';
    if ($docRoot !== '') {
        $roots[] = $docRoot;
    }

    return array_values(array_unique(array_filter($roots)));
}

function tv_image_proxy_log(string $message): void
{
    error_log('[tourvisor-image-proxy] ' . $message);
}

function tv_image_proxy_normalize_path(string $path): ?string
{
    $path = rawurldecode(trim($path));
    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');
    $path = preg_replace('#/+#', '/', $path) ?? $path;

    if ($path === '' || strpos($path, '..') !== false) {
        return null;
    }
    if (!preg_match('#^[a-zA-Z0-9_./-]+$#', $path)) {
        return null;
    }

    return $path;
}

function tv_image_proxy_content_type(string $pathOrUrl): string
{
    $ext = strtolower(pathinfo(parse_url($pathOrUrl, PHP_URL_PATH) ?: $pathOrUrl, PATHINFO_EXTENSION));
    if ($ext === 'png') {
        return 'image/png';
    }
    if ($ext === 'webp') {
        return 'image/webp';
    }
    if ($ext === 'gif') {
        return 'image/gif';
    }

    return 'image/jpeg';
}

/** Отсечь HTML-ошибки Tourvisor (~380 B) и прочий мусор. */
function tv_image_proxy_is_valid_image(string $bytes): bool
{
    $len = strlen($bytes);
    if ($len < 512) {
        return false;
    }
    if ($len >= 3 && $bytes[0] === "\xFF" && $bytes[1] === "\xD8" && $bytes[2] === "\xFF") {
        return true;
    }
    if ($len >= 8 && substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") {
        return true;
    }
    if ($len >= 6 && (substr($bytes, 0, 6) === 'GIF87a' || substr($bytes, 0, 6) === 'GIF89a')) {
        return true;
    }
    if ($len >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
        return true;
    }

    return false;
}

function tv_image_proxy_serve_bytes(string $bytes, string $contentType): void
{
    header('Cache-Control: public, max-age=86400');
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

function tv_image_proxy_alt_paths(string $normalizedPath): array
{
    if (!preg_match('#^(hotel_pics/)(verybig|main400|main|big)/(.*)$#i', $normalizedPath, $m)) {
        return [];
    }
    $prefix = $m[1];
    $current = strtolower($m[2]);
    $rest = $m[3];
    $alts = [];
    foreach (['verybig', 'main400', 'main', 'big'] as $size) {
        if ($size === $current) {
            continue;
        }
        $alts[] = $prefix . $size . '/' . $rest;
    }

    return $alts;
}

function tv_image_proxy_resolve_local(string $normalizedPath): ?string
{
    $checkedDirs = [];

    foreach (tv_image_proxy_local_roots() as $root) {
        $candidate = $root . '/' . $normalizedPath;
        $dir = dirname($candidate);
        if (!isset($checkedDirs[$dir])) {
            $checkedDirs[$dir] = true;
            if (is_dir($dir) && !is_readable($dir)) {
                tv_image_proxy_log('Directory exists but is not readable: ' . $dir);
            }
        }

        if (!is_file($candidate)) {
            continue;
        }

        $realRoot = realpath($root);
        $realFile = realpath($candidate);
        if ($realFile === false) {
            continue;
        }
        if ($realRoot !== false && strpos($realFile, $realRoot) !== 0) {
            tv_image_proxy_log('Path traversal blocked for: ' . $normalizedPath);
            continue;
        }
        if (!is_readable($realFile)) {
            tv_image_proxy_log('File exists but is not readable: ' . $realFile);
            continue;
        }

        return $realFile;
    }

    return null;
}

/**
 * @return array{0:?string,1:string} [localFile|null, pathUsed]
 */
function tv_image_proxy_resolve_local_with_alts(string $normalizedPath): array
{
    $local = tv_image_proxy_resolve_local($normalizedPath);
    if ($local !== null) {
        return [$local, $normalizedPath];
    }
    foreach (tv_image_proxy_alt_paths($normalizedPath) as $alt) {
        $hit = tv_image_proxy_resolve_local($alt);
        if ($hit !== null) {
            return [$hit, $alt];
        }
    }

    return [null, $normalizedPath];
}

function tv_image_proxy_serve_local(string $file, string $contentType): void
{
    $size = @filesize($file);
    $mtime = @filemtime($file);

    header('Cache-Control: public, max-age=86400');
    if ($mtime !== false) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    }
    if ($size !== false) {
        $etag = '"' . sha1((string) $mtime . '-' . (string) $size . '-' . basename($file)) . '"';
        header('ETag: ' . $etag);

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if (trim($ifNoneMatch) === $etag || ($ifModifiedSince && $mtime !== false && strtotime($ifModifiedSince) >= $mtime)) {
            http_response_code(304);
            exit;
        }
    }

    header('Content-Type: ' . $contentType);
    if ($size !== false) {
        header('Content-Length: ' . $size);
    }

    $fp = @fopen($file, 'rb');
    if ($fp === false) {
        tv_image_proxy_not_found('Failed to open local file: ' . $file);
    }
    fpassthru($fp);
    fclose($fp);
    exit;
}

/** Прозрачный GIF 1×1 для заглушки при 404. */
function tv_image_proxy_placeholder_body(): string
{
    return base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') ?: '';
}

function tv_image_proxy_not_found(string $reason = ''): void
{
    if ($reason !== '') {
        tv_image_proxy_log($reason);
    }

    http_response_code(404);
    header('Cache-Control: no-store');
    header('Content-Type: image/gif');

    $body = tv_image_proxy_placeholder_body();
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}

function tv_image_proxy_bad_request(string $message): void
{
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$url = trim((string) ($_GET['url'] ?? ''));
$pathParam = trim((string) ($_GET['path'] ?? ''));
$normalizedPath = null;

if ($pathParam !== '') {
    $normalizedPath = tv_image_proxy_normalize_path($pathParam);
    if ($normalizedPath === null) {
        tv_image_proxy_bad_request('Invalid path');
    }
}

if ($normalizedPath !== null) {
    [$localFile, $pathUsed] = tv_image_proxy_resolve_local_with_alts($normalizedPath);
    if ($localFile !== null) {
        tv_image_proxy_serve_local($localFile, tv_image_proxy_content_type($pathUsed));
    }
}

if ($url === '' && $normalizedPath !== null) {
    $url = 'http://' . $allowedHost . '/' . $normalizedPath;
}

if ($url !== '' && $normalizedPath === null) {
    if (preg_match('#^https?://' . preg_quote($allowedHost, '#') . '/(.+)$#i', $url, $m)) {
        $normalizedPath = tv_image_proxy_normalize_path($m[1]);
        if ($normalizedPath !== null) {
            [$localFile, $pathUsed] = tv_image_proxy_resolve_local_with_alts($normalizedPath);
            if ($localFile !== null) {
                tv_image_proxy_serve_local($localFile, tv_image_proxy_content_type($pathUsed));
            }
            $url = 'http://' . $allowedHost . '/' . $normalizedPath;
        }
    }
}

if ($url === '' || !preg_match('#^https?://' . preg_quote($allowedHost, '#') . '/#i', $url)) {
    tv_image_proxy_bad_request('Invalid url');
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'tourvisor_image_cache.php';

$cacheDir = th_tourvisor_image_cache_dir();
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$ttl = th_tourvisor_image_cache_ttl_seconds();

$fetchUrl = preg_replace('#^https:#', 'http:', $url);
$ctx = stream_context_create([
    'http' => [
        'timeout' => 10,
        'follow_location' => 1,
        'user_agent' => 'TravelHub-ImageProxy/1.0',
    ],
]);

$contentType = tv_image_proxy_content_type($url);
$ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

$cacheKey = sha1($fetchUrl) . ($ext ? ('.' . $ext) : '');
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey;

if (is_file($cacheFile)) {
    $mtime = @filemtime($cacheFile);
    if ($mtime !== false && (time() - $mtime) < $ttl) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false && tv_image_proxy_is_valid_image($cached)) {
            tv_image_proxy_serve_bytes($cached, $contentType);
        }
        @unlink($cacheFile);
    }
}

$image = @file_get_contents($fetchUrl, false, $ctx);

if ($image === false || $image === '' || !tv_image_proxy_is_valid_image($image)) {
    // Remote miss: попробовать соседние размеры (main400 ↔ verybig)
    if ($normalizedPath !== null) {
        foreach (tv_image_proxy_alt_paths($normalizedPath) as $altPath) {
            [$altLocal] = tv_image_proxy_resolve_local_with_alts($altPath);
            if ($altLocal !== null) {
                tv_image_proxy_serve_local($altLocal, tv_image_proxy_content_type($altPath));
            }
            $altUrl = 'http://' . $allowedHost . '/' . $altPath;
            $altFetch = preg_replace('#^https:#', 'http:', $altUrl);
            $altImage = @file_get_contents($altFetch, false, $ctx);
            if ($altImage !== false && $altImage !== '' && tv_image_proxy_is_valid_image($altImage)) {
                $image = $altImage;
                $fetchUrl = $altFetch;
                $url = $altUrl;
                $normalizedPath = $altPath;
                $contentType = tv_image_proxy_content_type($altPath);
                $ext = strtolower(pathinfo($altPath, PATHINFO_EXTENSION));
                $cacheKey = sha1($fetchUrl) . ($ext ? ('.' . $ext) : '');
                $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey;
                break;
            }
        }
    }
}

if ($image === false || $image === '' || !tv_image_proxy_is_valid_image($image)) {
    $pathsChecked = array_map(
        static fn(string $root): string => $root . '/' . ($normalizedPath ?? 'unknown'),
        tv_image_proxy_local_roots()
    );
    tv_image_proxy_not_found(
        'Image not found locally or remotely. path=' . ($normalizedPath ?? '')
        . ' checked=' . implode('; ', $pathsChecked)
        . ' url=' . $fetchUrl
    );
}

if (is_dir($cacheDir) && is_writable($cacheDir)) {
    $tmp = $cacheFile . '.tmp';
    $fp = @fopen($tmp, 'wb');
    if ($fp) {
        @flock($fp, LOCK_EX);
        fwrite($fp, $image);
        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);
        @rename($tmp, $cacheFile);
        @chmod($cacheFile, 0644);
    }

    // Фоновая подрезка ~1% запросов (полная — по cron: clear_image_cache.php)
    if (random_int(1, 100) === 1) {
        th_tourvisor_image_cache_maintain($cacheDir);
    }
}

tv_image_proxy_serve_bytes($image, $contentType);
