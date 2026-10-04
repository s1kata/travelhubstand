<?php
declare(strict_types=1);
/**
 * Сборка YML по правилам yandex_yml_feed_rules (promo_cache / promo-search).
 * Подбор: до tour_limit валидных офферов на правило (фото + URL + цена), дедуп: вылет + страна + ★ + отель.
 *
 * Публичная выдача /feed.yml только читает data/yandex_rules_feed_snapshot.yml (сборка — cron-yml-feed.php, yml_feed_rules_cron.php или CLI rebuild_feed.php).
 * По каждому уникальному source_departure_id пишется data/yandex_feed_dep_{id}.yml; при YML_FEED_DEPARTURE_ALIASES — дубликат data/yandex_feed_{slug}.yml (HTTP: /feed-{slug}.yml → export/feed-by-departure.php).
 * Обязательная запись: только снимок в data/. Копии export/yandex_business_rules_feed.yml и feed.yml в корне — опциональны (WARN при ошибке) только для главного снимка.
 * Новый снимок не заменяет старый, если сборка не прошла порог валидности (см. yandex_yml_rules_publish_gate_check); в лог пишется WARN stale_kept.
 *
 * Лог: data/yandex_yml_rules_feed.log
 * Блокировка: data/yandex_yml_rules_feed.lock (flock)
 */

require_once __DIR__ . '/yandex_yml_rules_schema.php';
require_once __DIR__ . '/yandex_feed_sync.php';
require_once __DIR__ . '/promo_speed_cache.php';
require_once __DIR__ . '/yandex_yml_promo_filters.php';
require_once __DIR__ . '/tourvisor_proxy_http_base.php';
require_once __DIR__ . '/th_feature_flags.php';

function yandex_yml_rules_project_root(): string
{
    return dirname(__DIR__, 2);
}

function yandex_yml_rules_lock_path(): string
{
    return yandex_yml_rules_project_root() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'yandex_yml_rules_feed.lock';
}

function yandex_yml_rules_log_path(): string
{
    return yandex_yml_rules_project_root() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'yandex_yml_rules_feed.log';
}

function yandex_yml_rules_log_line(string $line): void
{
    $path = yandex_yml_rules_log_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($path, '[' . $ts . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * @return resource|false
 */
function yandex_yml_rules_acquire_lock(bool $blocking)
{
    $path = yandex_yml_rules_lock_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $fh = @fopen($path, 'c+');
    if ($fh === false) {
        return false;
    }
    $flags = LOCK_EX;
    if (!$blocking) {
        $flags |= LOCK_NB;
    }
    if (!flock($fh, $flags)) {
        fclose($fh);

        return false;
    }

    return $fh;
}

function yandex_yml_rules_release_lock($fh): void
{
    if (is_resource($fh)) {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function yml_rules_is_absolute_url(string $url): bool
{
    $l = strtolower($url);

    return strpos($l, 'http://') === 0 || strpos($l, 'https://') === 0;
}

function yandex_yml_rules_feed_snapshot_path(): string
{
    return yandex_yml_rules_project_root() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'yandex_rules_feed_snapshot.yml';
}

/** Снимок YML только для правил с данным Tourvisor departureId. */
function yandex_yml_rules_feed_snapshot_path_for_departure(int $departureId): string
{
    $departureId = max(0, $departureId);

    return yandex_yml_rules_project_root() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'yandex_feed_dep_' . $departureId . '.yml';
}

/** Имя файла data/yandex_feed_{slug}.yml для человекочитаемого URL (slug из YML_FEED_DEPARTURE_ALIASES). */
function yandex_yml_rules_feed_snapshot_path_for_slug(string $slug): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '', $slug));

    return yandex_yml_rules_project_root() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'yandex_feed_' . $slug . '.yml';
}

/**
 * YML_FEED_DEPARTURE_ALIASES в .env: "samara:12,moscow:28" (slug:departureId).
 *
 * @return array<int, string> departureId => slug
 */
function yandex_yml_rules_departure_alias_map(): array
{
    $raw = trim((string) (getenv('YML_FEED_DEPARTURE_ALIASES') ?: ($_ENV['YML_FEED_DEPARTURE_ALIASES'] ?? '')));
    // По умолчанию: Самара и Москва (ротация пишет /feed-samara.yml и /feed-moscow.yml)
    if ($raw === '') {
        $raw = 'samara:7,moscow:1';
    }
    $out = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if ($part === '' || !preg_match('/^([a-z0-9-]+):(\d+)$/i', $part, $m)) {
            continue;
        }
        $slug = strtolower($m[1]);
        $id = (int) $m[2];
        if ($id > 0 && $slug !== '') {
            $out[$id] = $slug;
        }
    }

    return $out;
}

/** Slug для departureId с учётом th_departure_normalize_id (12→7, 28→1). */
function yandex_yml_rules_departure_slug(int $departureId): ?string
{
    $map = yandex_yml_rules_departure_alias_map();
    if ($map === []) {
        return null;
    }
    if (isset($map[$departureId])) {
        return $map[$departureId];
    }
    $norm = th_departure_normalize_id($departureId);
    if ($norm !== $departureId && isset($map[$norm])) {
        return $map[$norm];
    }

    return null;
}

/** Slug из YML_FEED_DEPARTURE_ALIASES (для /feed-{slug}.yml). */
function yandex_yml_rules_departure_public_slugs(): array
{
    $slugs = array_values(yandex_yml_rules_departure_alias_map());

    return array_values(array_unique(array_filter(array_map(static fn ($s): string => strtolower((string) $s), $slugs))));
}

function yandex_yml_rules_public_departure_feed_snapshot_path(string $slug): ?string
{
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '', $slug));
    if ($slug === '' || !in_array($slug, yandex_yml_rules_departure_public_slugs(), true)) {
        return null;
    }

    return yandex_yml_rules_feed_snapshot_path_for_slug($slug);
}

/** Минимальный размер валидного снимка YML (байты), ниже — считаем битым (0 b, обрыв). */
function yandex_yml_rules_snapshot_min_bytes(): int
{
    return 80;
}

function yandex_yml_rules_feed_snapshot_is_valid(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }
    clearstatcache(true, $path);
    $sz = @filesize($path);

    return $sz !== false && $sz >= yandex_yml_rules_snapshot_min_bytes();
}

function yandex_yml_rules_publish_min_offers(): int
{
    $v = (int) (getenv('YML_RULES_MIN_OFFERS_FOR_PUBLISH') ?: ($_ENV['YML_RULES_MIN_OFFERS_FOR_PUBLISH'] ?? 1));

    return max(0, min(5000, $v));
}

function yandex_yml_rules_publish_min_xml_bytes(): int
{
    $v = (int) (getenv('YML_RULES_PUBLISH_MIN_XML_BYTES') ?: ($_ENV['YML_RULES_PUBLISH_MIN_XML_BYTES'] ?? 1024));

    return max(256, min(10485760, $v));
}

/** Минимальный размер публичного снимка по slug (feed-samara.yml / feed-moscow.yml). */
function yandex_yml_rules_public_slug_snapshot_min_bytes(): int
{
    return 100;
}

function yandex_yml_rules_departure_aliases_configured(): bool
{
    return yandex_yml_rules_departure_alias_map() !== [];
}

/**
 * Порог офферов перед заменой data/yandex_feed_{slug}.yml (отдельно от общего /feed.yml).
 */
function yandex_yml_rules_publish_min_offers_for_slug(string $slug): int
{
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '', $slug));
    $envKey = $slug === 'moscow' ? 'YML_RULES_MIN_OFFERS_MOSCOW' : 'YML_RULES_MIN_OFFERS_SAMARA';
    $default = 5;
    if ($slug !== 'samara' && $slug !== 'moscow') {
        return $default;
    }
    $v = (int) (getenv($envKey) ?: ($_ENV[$envKey] ?? $default));

    return max(0, min(5000, $v));
}

/**
 * Публичный снимок по slug: существует и не меньше порога для HTTP-выдачи.
 */
function yandex_yml_rules_public_slug_snapshot_is_ready(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }
    clearstatcache(true, $path);
    $sz = @filesize($path);

    return $sz !== false && $sz >= yandex_yml_rules_public_slug_snapshot_min_bytes();
}

/**
 * @return array{
 *   slug: string,
 *   label: string,
 *   path: ?string,
 *   aliases_configured: bool,
 *   alias_known: bool,
 *   exists: bool,
 *   ready: bool,
 *   file_size: int,
 *   catalog_date: string,
 *   offers_count: int,
 *   public_url_path: string
 * }
 */
function yandex_yml_rules_city_feed_status(string $slug): array
{
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '', $slug));
    $labels = ['samara' => 'Самара', 'moscow' => 'Москва'];
    $path = yandex_yml_rules_public_departure_feed_snapshot_path($slug);
    $aliasesConfigured = yandex_yml_rules_departure_aliases_configured();
    $status = [
        'slug' => $slug,
        'label' => $labels[$slug] ?? $slug,
        'path' => $path,
        'aliases_configured' => $aliasesConfigured,
        'alias_known' => $path !== null,
        'exists' => false,
        'ready' => false,
        'file_size' => 0,
        'catalog_date' => '',
        'offers_count' => 0,
        'public_url_path' => '/feed-' . $slug . '.yml',
    ];
    if ($path === null || !is_file($path)) {
        return $status;
    }
    clearstatcache(true, $path);
    $sz = @filesize($path);
    $status['exists'] = true;
    $status['file_size'] = $sz !== false ? (int) $sz : 0;
    $status['ready'] = yandex_yml_rules_public_slug_snapshot_is_ready($path);
    $xml = @file_get_contents($path);
    if (is_string($xml) && $xml !== '') {
        $status['offers_count'] = substr_count($xml, '<offer ');
        if (preg_match('/<yml_catalog[^>]*\sdate="([^"]+)"/', $xml, $m)) {
            $status['catalog_date'] = $m[1];
        } elseif (preg_match('/date="([^"]+)"/', $xml, $m)) {
            $status['catalog_date'] = $m[1];
        }
        if ($status['catalog_date'] === '' && $sz !== false) {
            $mtime = @filemtime($path);
            if ($mtime !== false) {
                $status['catalog_date'] = date('Y-m-d H:i', $mtime);
            }
        }
    }

    return $status;
}

/**
 * Копия data/yandex_feed_dep_{id}.yml → data/yandex_feed_{slug}.yml с отдельным порогом офферов.
 *
 * @return array{ok: bool, slug: string, stale_kept_slug: bool, offers: int, file?: string, error?: string}
 */
function yandex_yml_rules_publish_slug_snapshot_from_departure(
    int $departureId,
    string $slug,
    string $departureSnapshotPath,
    int $offersCandidate
): array {
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '', $slug));
    $slugPath = yandex_yml_rules_feed_snapshot_path_for_slug($slug);
    if ($slug === '' || !in_array($slug, yandex_yml_rules_departure_public_slugs(), true)) {
        return ['ok' => true, 'slug' => $slug, 'stale_kept_slug' => false, 'offers' => $offersCandidate];
    }
    if (!is_file($departureSnapshotPath) || !yandex_yml_rules_feed_snapshot_is_valid($departureSnapshotPath)) {
        yandex_yml_rules_log_line('WARN slug_skip slug=' . $slug . ' dep=' . $departureId . ' reason=dep_snapshot_missing');

        return ['ok' => false, 'slug' => $slug, 'stale_kept_slug' => false, 'offers' => $offersCandidate, 'error' => 'dep_snapshot_missing'];
    }

    $minOffers = yandex_yml_rules_publish_min_offers_for_slug($slug);
    if ($offersCandidate < $minOffers) {
        if (yandex_yml_rules_public_slug_snapshot_is_ready($slugPath)) {
            yandex_yml_rules_log_line('WARN stale_kept_' . $slug . ' dep=' . $departureId . ' offers=' . $offersCandidate . ' min=' . $minOffers);

            return ['ok' => true, 'slug' => $slug, 'stale_kept_slug' => true, 'offers' => $offersCandidate, 'file' => $slugPath];
        }
        yandex_yml_rules_log_line('WARN slug_publish_rejected slug=' . $slug . ' offers=' . $offersCandidate . ' min=' . $minOffers . ' (no slug snapshot to keep)');

        return ['ok' => false, 'slug' => $slug, 'stale_kept_slug' => false, 'offers' => $offersCandidate, 'error' => 'slug_publish_gate:offers'];
    }

    $xmlCopy = @file_get_contents($departureSnapshotPath);
    if ($xmlCopy === false || $xmlCopy === '') {
        yandex_yml_rules_log_line('WARN slug_copy_read_fail slug=' . $slug . ' dep=' . $departureId);

        return ['ok' => false, 'slug' => $slug, 'stale_kept_slug' => false, 'offers' => $offersCandidate, 'error' => 'slug_copy_read'];
    }
    if (!yandex_yml_rules_atomic_file_put($slugPath, $xmlCopy)) {
        yandex_yml_rules_log_line('WARN departure_alias_write dep=' . $departureId . ' slug=' . $slug);

        return ['ok' => false, 'slug' => $slug, 'stale_kept_slug' => false, 'offers' => $offersCandidate, 'error' => 'slug_copy_write'];
    }
    yandex_yml_rules_log_line('OK departure_alias dep=' . $departureId . ' slug=' . $slug . ' file=' . $slugPath . ' offers=' . $offersCandidate);

    return ['ok' => true, 'slug' => $slug, 'stale_kept_slug' => false, 'offers' => $offersCandidate, 'file' => $slugPath];
}

/**
 * Порог перед заменой снимка: число офферов, размер XML, well-formed yml_catalog.
 *
 * @return array{ok: bool, reason: string}
 */
function yandex_yml_rules_publish_gate_check(string $xml, int $offers): array
{
    $minO = yandex_yml_rules_publish_min_offers();
    if ($offers < $minO) {
        return ['ok' => false, 'reason' => 'offers'];
    }
    $minB = yandex_yml_rules_publish_min_xml_bytes();
    if (strlen($xml) < $minB) {
        return ['ok' => false, 'reason' => 'size'];
    }
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $loaded = $doc->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$loaded || $doc->documentElement === null) {
        return ['ok' => false, 'reason' => 'xml'];
    }
    if ($doc->documentElement->localName !== 'yml_catalog') {
        return ['ok' => false, 'reason' => 'xml_root'];
    }

    return ['ok' => true, 'reason' => ''];
}

/**
 * Атомарная запись: tmp в том же каталоге → rename, без «висящего» пустого целевого файла при обрыве.
 */
function yandex_yml_rules_atomic_file_put(string $finalPath, string $contents): bool
{
    $byteLen = strlen($contents);
    if ($byteLen < 1) {
        return false;
    }
    $dir = dirname($finalPath);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    try {
        $suffix = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $suffix = (string) mt_rand();
    }
    $tmp = $dir . DIRECTORY_SEPARATOR . '.yml_rules_' . $suffix . '.tmp';
    $w = @file_put_contents($tmp, $contents, LOCK_EX);
    if ($w === false || $w !== $byteLen) {
        @unlink($tmp);

        return false;
    }
    clearstatcache(true, $tmp);
    if (!is_file($tmp)) {
        return false;
    }
    $fs = @filesize($tmp);
    if ($fs === false || (int) $fs !== $byteLen) {
        @unlink($tmp);

        return false;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        if (is_file($finalPath)) {
            @unlink($finalPath);
        }
        if (!@rename($tmp, $finalPath)) {
            if (!@copy($tmp, $finalPath)) {
                @unlink($tmp);

                return false;
            }
            @unlink($tmp);
        }
    } else {
        if (!@rename($tmp, $finalPath)) {
            if (is_file($finalPath)) {
                @unlink($finalPath);
            }
            if (!@rename($tmp, $finalPath)) {
                @unlink($tmp);

                return false;
            }
        }
    }
    clearstatcache(true, $finalPath);

    return is_file($finalPath) && (int) @filesize($finalPath) === $byteLen;
}

/**
 * @param list<array<string, mixed>> $rows rows from yandex_feed_parse_search_response shape + country_id, country_name
 *
 * @return array{xml: string, offers: int}
 */
function yandex_yml_rules_render_combined_yml_catalog(array $rows, string $siteBase): array
{
    $shopName = (string) (getenv('YML_SHOP_NAME') ?: ($_ENV['YML_SHOP_NAME'] ?? 'Travel Hub'));
    $shopCompany = (string) (getenv('YML_SHOP_COMPANY') ?: ($_ENV['YML_SHOP_COMPANY'] ?? $shopName));
    $baseUrl = rtrim($siteBase, '/');

    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->startDocument('1.0', 'UTF-8');
    $writer->setIndent(true);
    $writer->startElement('yml_catalog');
    $writer->writeAttribute('date', date('Y-m-d H:i'));
    $writer->startElement('shop');
    $writer->writeElement('name', $shopName);
    $writer->writeElement('company', $shopCompany);
    $writer->writeElement('url', $baseUrl);
    $writer->startElement('currencies');
    $writer->startElement('currency');
    $writer->writeAttribute('id', 'RUB');
    $writer->writeAttribute('rate', '1');
    $writer->endElement();
    $writer->endElement();

    $writer->startElement('categories');
    $catIds = [];
    foreach ($rows as $row) {
        $cid = (int) ($row['country_id'] ?? 0);
        $catId = (string) (200000 + max(0, $cid));
        $label = trim((string) ($row['country_name'] ?? ''));
        if ($label === '') {
            $label = $cid > 0 ? ('Страна ' . $cid) : 'Туры';
        }
        $catIds[$catId] = $label;
    }
    if ($catIds === []) {
        $catIds['200000'] = 'Туры';
    }
    ksort($catIds, SORT_NATURAL);
    foreach ($catIds as $catId => $label) {
        $writer->startElement('category');
        $writer->writeAttribute('id', (string) $catId);
        $writer->text($label);
        $writer->endElement();
    }
    $writer->endElement();

    $writer->startElement('offers');
    $n = 0;
    foreach ($rows as $row) {
        $tid = trim((string) ($row['tour_id'] ?? ''));
        $title = trim((string) ($row['title'] ?? ''));
        $priceFloat = (float) ($row['price'] ?? 0);
        $url = trim((string) ($row['offer_url'] ?? ''));
        if ($tid === '' || $title === '' || $priceFloat <= 0 || $url === '') {
            continue;
        }
        if (!yml_rules_is_absolute_url($url)) {
            $url = $baseUrl . '/' . ltrim($url, '/');
        }
        $pic = trim((string) ($row['picture'] ?? ''));
        if ($pic !== '' && !yml_rules_is_absolute_url($pic)) {
            $pic = $baseUrl . '/' . ltrim($pic, '/');
        }
        if (!yandex_feed_is_valid_tour_picture($pic)) {
            continue;
        }
        $desc = trim((string) ($row['description'] ?? ''));
        if ($desc === '') {
            $desc = mb_substr($title, 0, 500);
        } else {
            $desc = mb_substr($desc, 0, 2000);
        }
        $cid = (int) ($row['country_id'] ?? 0);
        $categoryId = (string) (200000 + max(0, $cid));
        $offerId = 'ybr-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $tid);
        if ($offerId === 'ybr-' || $offerId === 'ybr') {
            $offerId = 'ybr-' . substr(sha1($tid), 0, 16);
        }

        $writer->startElement('offer');
        $writer->writeAttribute('id', $offerId);
        $writer->writeAttribute('available', 'true');
        $writer->writeElement('url', $url);
        $writer->writeElement('price', number_format($priceFloat, 2, '.', ''));
        $writer->writeElement('currencyId', 'RUB');
        $writer->writeElement('categoryId', $categoryId);
        $writer->writeElement('name', mb_substr($title, 0, 500));
        $writer->writeElement('description', $desc);
        $writer->writeElement('picture', $pic);
        $writer->endElement();
        $n++;
    }
    $writer->endElement();
    $writer->endElement();
    $writer->endElement();
    $writer->endDocument();

    return ['xml' => $writer->outputMemory(), 'offers' => $n];
}

/**
 * @param list<string> $optionalMirrorPaths дополнительные копии XML (пусто — только $snapshotPath)
 * @param int $bootstrapEmpty 1 — первый запуск без валидного снимка: записать пустой каталог без порога (включено правил нет)
 *
 * @return array{
 *   ok: bool,
 *   file?: string,
 *   files?: list<string>,
 *   offers?: int,
 *   error?: string,
 *   write_errors?: list<string>,
 *   stale_kept?: bool
 * }
 */
function yandex_yml_rules_write_combined_yml_to(string $snapshotPath, array $rows, string $siteBase, int $bootstrapEmpty, array $optionalMirrorPaths): array
{
    $root = yandex_yml_rules_project_root();
    $built = yandex_yml_rules_render_combined_yml_catalog($rows, $siteBase);
    $xml = $built['xml'];
    $n = $built['offers'];
    $snap = $snapshotPath;

    if ($bootstrapEmpty === 1) {
        $passGate = true;
        $gateReason = 'bootstrap';
    } else {
        $g = yandex_yml_rules_publish_gate_check($xml, $n);
        $passGate = $g['ok'];
        $gateReason = $g['reason'];
    }

    if (!$passGate) {
        if (yandex_yml_rules_feed_snapshot_is_valid($snap)) {
            yandex_yml_rules_log_line('WARN stale_kept path=' . $snap . ' reason=' . $gateReason . ' offers=' . $n);

            return ['ok' => true, 'stale_kept' => true, 'offers' => $n, 'files' => [], 'write_errors' => []];
        }
        yandex_yml_rules_log_line('WARN publish_rejected path=' . $snap . ' reason=' . $gateReason . ' offers=' . $n . ' (no valid snapshot to keep)');

        return ['ok' => false, 'error' => 'publish_gate:' . $gateReason, 'offers' => $n];
    }

    if (!yandex_yml_rules_atomic_file_put($snap, $xml)) {
        yandex_yml_rules_log_line('FAIL write snapshot: ' . $snap);

        return ['ok' => false, 'error' => 'Cannot write feed snapshot: ' . $snap, 'offers' => $n];
    }

    $written = [$snap];
    $writeErrors = [];
    $exportDir = $root . DIRECTORY_SEPARATOR . 'export';

    foreach ($optionalMirrorPaths as $filePath) {
        $dir = dirname($filePath);
        if (strpos($filePath, DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR) !== false) {
            if (!is_dir($exportDir) && !@mkdir($exportDir, 0755, true) && !is_dir($exportDir)) {
                $writeErrors[] = 'optional skip (no export dir): ' . $filePath;
                continue;
            }
        } elseif (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $writeErrors[] = 'optional skip (no dir): ' . $dir;
            continue;
        }
        if (yandex_yml_rules_atomic_file_put($filePath, $xml)) {
            $written[] = $filePath;
        } else {
            $writeErrors[] = 'Cannot write (optional): ' . $filePath;
        }
    }

    if ($writeErrors !== []) {
        yandex_yml_rules_log_line('WARN optional mirrors: ' . implode(' | ', $writeErrors));
    }

    return ['ok' => true, 'files' => $written, 'file' => $snap, 'offers' => $n, 'write_errors' => $writeErrors];
}

/**
 * Главный фид /feed.yml: снимок + зеркала в export/ и корень (как раньше).
 *
 * @param list<array<string, mixed>> $rows rows from yandex_feed_parse_search_response shape + country_id, country_name
 *
 * @return array{
 *   ok: bool,
 *   file?: string,
 *   files?: list<string>,
 *   offers?: int,
 *   error?: string,
 *   write_errors?: list<string>,
 *   stale_kept?: bool
 * }
 */
function yandex_yml_rules_write_combined_yml(array $rows, string $siteBase, int $bootstrapEmpty = 0): array
{
    $root = yandex_yml_rules_project_root();
    $exportDir = $root . DIRECTORY_SEPARATOR . 'export';
    $optional = [
        $exportDir . DIRECTORY_SEPARATOR . 'yandex_business_rules_feed.yml',
        $root . DIRECTORY_SEPARATOR . 'feed.yml',
    ];

    return yandex_yml_rules_write_combined_yml_to(yandex_yml_rules_feed_snapshot_path(), $rows, $siteBase, $bootstrapEmpty, $optional);
}

/**
 * Фильтры списка отелей для YML: TR/EG ≥6 ночей; VN — прямой перелёт; операторы — как на сайте.
 *
 * @param array<int, array<string, mixed>> $hotels
 * @return array<int, array<string, mixed>>
 */
function yandex_yml_rules_apply_promo_hotel_filters(
    array $hotels,
    int $countryId,
    int $depId,
    string $cityLabel,
    string $proxyBase,
    int $timeout
): array {
    $hotels = yandex_yml_filter_hotels_tr_eg_min_nights($hotels, $countryId);
    $hotels = th_promo_filter_hotels_by_allowed_operators($hotels, $countryId);
    if (yandex_yml_is_vietnam_country($countryId)) {
        $hotels = yandex_yml_filter_hotels_vietnam_direct($hotels, $depId, $cityLabel, $proxyBase, $timeout);
    }
    if ($countryId === th_promo_sochi_country_id()) {
        $hotels = th_promo_filter_hotels_sochi_resort_only($hotels);
    }

    return $hotels;
}

/**
 * HTTP-dispatch к tourvisor-proxy для star-boost / search-cached при сборке YML.
 *
 * @return callable(array<string, string>): array
 */
function yandex_yml_rules_promo_dispatch(string $proxyBase, int $timeout): callable
{
    return static function (array $params) use ($proxyBase, $timeout): array {
        $url = $proxyBase . (strpos($proxyBase, '?') !== false ? '&' : '?') . http_build_query($params);
        $raw = yandex_feed_http_get($url, $timeout);
        if ($raw === null) {
            return ['success' => false, 'data' => []];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['success' => false, 'data' => []];
    };
}

/**
 * Отели акций для правила YML: promo_cache (как promo-search на странице акций), без live /tours/hots.
 *
 * @return array{hotels: array<int, array<string, mixed>>, dateFrom: string, dateTo: string, source: string}|null
 */
function yandex_yml_rules_fetch_promo_hotels_for_rule(
    int $countryId,
    int $depId,
    string $proxyBase,
    int $timeout
): ?array {
    $depNorm = th_departure_normalize_id($depId);
    $promoDates = th_promo_speed_promo_dates($countryId);
    $dispatch = yandex_yml_rules_promo_dispatch($proxyBase, $timeout);

    $cache = th_promo_speed_cache_get_best($countryId, $depNorm, false);
    $source = 'promo_cache';
    if ($cache === null) {
        $cache = th_promo_speed_cache_get_best($countryId, $depNorm, true);
        $source = 'promo_cache_stale';
    }

    if ($cache !== null) {
        $dateFrom = (string) ($cache['dateFrom'] ?? $promoDates['dateFrom']);
        $dateTo = (string) ($cache['dateTo'] ?? $promoDates['dateTo']);
        $hotels = th_promo_speed_hotels_from_cache_payload(
            $cache,
            $countryId,
            $depNorm,
            ['dateFrom' => $dateFrom, 'dateTo' => $dateTo],
            $dispatch
        );
        if ($hotels !== []) {
            return [
                'hotels' => $hotels,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'source' => $source,
            ];
        }
    }

    $params = [
        'type' => 'promo-search',
        'departureId' => (string) $depNorm,
        'countryId' => (string) $countryId,
        'dateFrom' => $promoDates['dateFrom'],
        'dateTo' => $promoDates['dateTo'],
        'adults' => '2',
        'cacheOnly' => '1',
    ];
    $url = $proxyBase . (strpos($proxyBase, '?') !== false ? '&' : '?') . http_build_query($params);
    $raw = yandex_feed_http_get($url, $timeout);
    if ($raw === null) {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !is_array($decoded['data'] ?? null) || $decoded['data'] === []) {
        return null;
    }

    return [
        'hotels' => $decoded['data'],
        'dateFrom' => $promoDates['dateFrom'],
        'dateTo' => $promoDates['dateTo'],
        'source' => 'promo_search_cache_only',
    ];
}

/**
 * Promo-кэш акций + парсинг по одному правилу yandex_yml_feed_rules.
 *
 * @param array<string, mixed> $rule
 * @param list<string> $errors
 * @return list<array<string, mixed>>
 */
function yandex_yml_rules_build_rows_for_single_rule(
    array $rule,
    string $proxyBase,
    int $timeout,
    string $datesFrom,
    string $datesTo,
    bool $syncLive,
    string $siteBase,
    string $imageProxyBase,
    array &$errors
): array {
    $rid = (int) ($rule['id'] ?? 0);
    $depId = (int) ($rule['source_departure_id'] ?? 0);
    $countryId = (int) ($rule['target_country_id'] ?? 0);
    $cityLabel = trim((string) ($rule['source_city'] ?? ''));
    $countryLabel = trim((string) ($rule['target_country'] ?? ''));
    $lim = (int) ($rule['tour_limit'] ?? 20);
    $lim = max(1, min(500, $lim));

    if ($depId <= 0 || $countryId <= 0) {
        $errors[] = "rule #{$rid}: invalid departure or country";
        yandex_yml_rules_log_line("FAIL rule_id={$rid} {$cityLabel}→{$countryLabel} invalid_ids");

        return [];
    }

    $promoHit = yandex_yml_rules_fetch_promo_hotels_for_rule($countryId, $depId, $proxyBase, $timeout);
    if ($promoHit === null) {
        $errors[] = "rule #{$rid}: promo cache miss and promo-search failed";
        yandex_yml_rules_log_line("FAIL rule_id={$rid} {$cityLabel}→{$countryLabel} promo_empty");

        return [];
    }

    $hotels = yandex_yml_rules_apply_promo_hotel_filters(
        $promoHit['hotels'],
        $countryId,
        $depId,
        $cityLabel,
        $proxyBase,
        $timeout
    );
    if ($hotels === []) {
        yandex_yml_rules_log_line("WARN rule_id={$rid} {$cityLabel}→{$countryLabel} source={$promoHit['source']} filtered_empty");

        return [];
    }

    $ruleDatesFrom = $promoHit['dateFrom'] !== '' ? $promoHit['dateFrom'] : $datesFrom;
    $ruleDatesTo = $promoHit['dateTo'] !== '' ? $promoHit['dateTo'] : $datesTo;

    $exportOverride = [
        'min' => null,
        'max' => null,
        'limit' => $lim,
        'require_stars' => false,
        'sort_price' => true,
        'russia_sochi_cap' => false,
    ];
    $starsF = isset($rule['hotel_stars_filter']) ? (int) $rule['hotel_stars_filter'] : 0;
    if (th_feature_yml_rule_hotel_stars_filter() && in_array($starsF, [3, 4, 5], true)) {
        $exportOverride['require_stars'] = true;
        $exportOverride['min'] = $starsF;
        $exportOverride['max'] = $starsF;
    }
    $cname = $countryLabel !== '' ? $countryLabel : ('Страна ' . $countryId);

    $rows = yandex_yml_rules_collect_valid_offers_from_hotels(
        $hotels,
        $countryId,
        $cname,
        $ruleDatesFrom,
        $ruleDatesTo,
        $siteBase,
        $imageProxyBase,
        $lim,
        $exportOverride,
        $cityLabel !== '' ? $cityLabel : null,
        $depId,
        $starsF,
        $rid
    );
    yandex_yml_rules_log_line(
        "OK rule_id={$rid} {$cityLabel}→{$countryLabel} source={$promoHit['source']} hotels="
        . count($hotels) . ' valid=' . count($rows) . ' target=' . $lim
        . ($starsF > 0 ? " stars={$starsF}" : '')
    );

    return $rows;
}

/**
 * Те же проверки, что при записи <offer> в yandex_yml_rules_render_combined_yml_catalog.
 */
function yandex_yml_rules_row_passes_xml_gate(array $row, string $siteBase): bool
{
    $tid = trim((string) ($row['tour_id'] ?? ''));
    $title = trim((string) ($row['title'] ?? ''));
    $priceFloat = (float) ($row['price'] ?? 0);
    $url = trim((string) ($row['offer_url'] ?? ''));
    if ($tid === '' || $title === '' || $priceFloat <= 0 || $url === '') {
        return false;
    }
    if (!yml_rules_is_absolute_url($url)) {
        $url = rtrim($siteBase, '/') . '/' . ltrim($url, '/');
    }
    $pic = trim((string) ($row['picture'] ?? ''));
    if ($pic !== '' && !yml_rules_is_absolute_url($pic)) {
        $pic = rtrim($siteBase, '/') . '/' . ltrim($pic, '/');
    }

    return yandex_feed_is_valid_tour_picture($pic);
}

/**
 * Подбор офферов для одного правила: перебор отелей по цене, пока не наберётся $targetCount
 * валидных (фото + URL + цена). Один отель — один оффер (самый дешёвый валидный тур).
 *
 * @param array<int, array<string, mixed>> $hotels
 * @param array{min: ?int, max: ?int, limit: int, require_stars: bool, sort_price: bool, russia_sochi_cap: bool} $starRule
 * @return list<array<string, mixed>>
 */
function yandex_yml_rules_collect_valid_offers_from_hotels(
    array $hotels,
    int $countryId,
    string $countryName,
    string $dateFrom,
    string $dateTo,
    string $siteBase,
    string $imageProxyBase,
    int $targetCount,
    array $starRule,
    ?string $departureCityForUrl,
    int $departureId,
    int $starsFilter,
    int $ruleId
): array {
    $targetCount = max(1, min(500, $targetCount));
    $depNorm = th_departure_normalize_id($departureId);
    $candidates = [];

    foreach ($hotels as $h) {
        if (!is_array($h)) {
            continue;
        }
        $stars = yandex_feed_extract_hotel_stars($h);
        if (!yandex_feed_row_passes_star_rule($stars, $starRule)) {
            continue;
        }
        $fromHotel = 0;
        if (!empty($h['country']) && is_array($h['country'])) {
            $fromHotel = (int) ($h['country']['id'] ?? 0);
        }
        if ($fromHotel <= 0) {
            $fromHotel = (int) ($h['countryId'] ?? 0);
        }
        if ($countryId > 0 && ($fromHotel <= 0 || $fromHotel !== $countryId)) {
            continue;
        }
        $tours = $h['tours'] ?? [];
        if (!is_array($tours) || $tours === []) {
            continue;
        }
        $region = '';
        if (!empty($h['region']) && is_array($h['region'])) {
            $region = trim((string) ($h['region']['name'] ?? $h['region']['russianName'] ?? ''));
        }
        $cname = $countryName;
        if (!empty($h['country']) && is_array($h['country'])) {
            $cn = trim((string) ($h['country']['name'] ?? $h['country']['russianName'] ?? ''));
            if ($cn !== '') {
                $cname = $cn;
            }
        }

        $bestRow = null;
        $bestPrice = PHP_FLOAT_MAX;
        foreach ($tours as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $row = yandex_feed_row_from_hotel_tour(
                $h,
                $tour,
                $cname,
                $dateFrom,
                $dateTo,
                $siteBase,
                $imageProxyBase,
                $departureCityForUrl,
                true
            );
            if ($row === null || !yandex_yml_rules_row_passes_xml_gate($row, $siteBase)) {
                continue;
            }
            $p = (float) $row['price'];
            if ($p < $bestPrice) {
                $bestPrice = $p;
                $bestRow = $row;
            }
        }
        if ($bestRow === null) {
            continue;
        }
        $bestRow['country_id'] = $countryId > 0 ? $countryId : $fromHotel;
        $bestRow['country_name'] = $cname;
        $bestRow['_region'] = $region;
        $bestRow['_stars'] = $stars;
        $candidates[] = $bestRow;
    }

    usort($candidates, static function (array $a, array $b): int {
        return ((float) $a['price']) <=> ((float) $b['price']);
    });

    $out = [];
    $seenHotel = [];
    foreach ($candidates as $row) {
        if (count($out) >= $targetCount) {
            break;
        }
        $hid = (int) ($row['hotel_id'] ?? 0);
        if ($hid > 0) {
            if (isset($seenHotel[$hid])) {
                continue;
            }
            $seenHotel[$hid] = true;
        }
        $row['source_departure_id'] = $depNorm;
        $row['rule_stars_filter'] = $starsFilter;
        $row['rule_id'] = $ruleId;
        unset($row['_region'], $row['_stars']);
        $out[] = $row;
    }

    return $out;
}

/**
 * Ключ дедупликации: вылет + страна + звёзды правила + отель (или tour_id).
 */
function yandex_yml_rules_row_dedupe_key(array $row): string
{
    $dep = (int) ($row['source_departure_id'] ?? 0);
    $cid = (int) ($row['country_id'] ?? 0);
    $stars = (int) ($row['rule_stars_filter'] ?? 0);
    $hid = (int) ($row['hotel_id'] ?? 0);
    $tid = (string) ($row['tour_id'] ?? '');
    if ($dep > 0 && $cid > 0 && $hid > 0) {
        return 'd:' . $dep . ':c:' . $cid . ':s:' . $stars . ':h:' . $hid;
    }
    if ($dep > 0 && $tid !== '') {
        return 'd:' . $dep . ':t:' . $tid;
    }
    if ($tid !== '') {
        return 't:' . $tid;
    }

    return 'x:' . md5(json_encode($row, JSON_UNESCAPED_UNICODE));
}

/**
 * Дедупликация офферов для YML: один отель на вылет + страну + звёздность правила;
 * при совпадении ключа остаётся оффер с минимальной ценой.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function yandex_yml_rules_merge_dedupe(array $rows): array
{
    $best = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = yandex_yml_rules_row_dedupe_key($row);
        if (!isset($best[$key]) || (float) $row['price'] < (float) $best[$key]['price']) {
            $best[$key] = $row;
        }
    }

    return array_values($best);
}

/**
 * @return array{
 *   ok: bool,
 *   rules_total?: int,
 *   rules_ok?: int,
 *   offers_written?: int,
 *   offers_candidate?: int,
 *   errors?: list<string>,
 *   files?: list<string>,
 *   lock_busy?: bool,
 *   stale_kept?: bool,
 *   message?: string,
 *   departure_feeds?: list<array<string, mixed>>
 * }
 */
function yandex_yml_rules_run(PDO $pdo, bool $blockingLock = true): array
{
    $errors = [];
    yandex_yml_rules_ensure_table($pdo);

    $fh = yandex_yml_rules_acquire_lock($blockingLock);
    if ($fh === false) {
        yandex_yml_rules_log_line('SKIP lock_busy (another run in progress)');

        return ['ok' => false, 'lock_busy' => true, 'message' => 'Парсер уже выполняется (lock). Повторите позже.'];
    }

    try {
        $siteBase = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? 'https://travelhub63.ru')), '/');
        if (preg_match('#/frontend/?$#i', $siteBase)) {
            $siteBase = (string) preg_replace('#/frontend/?$#i', '', $siteBase);
            $siteBase = rtrim($siteBase, '/');
        }
        $imageProxyBase = $siteBase . '/backend/api/tourvisor-image-proxy.php';

        $stmt = $pdo->query('SELECT * FROM yandex_yml_feed_rules WHERE enabled = 1 ORDER BY sort_order ASC, id ASC');
        $rules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($rules === []) {
            $snapPath = yandex_yml_rules_feed_snapshot_path();
            $bootstrap = yandex_yml_rules_feed_snapshot_is_valid($snapPath) ? 0 : 1;
            yandex_yml_rules_log_line('OK rules=0 offers=0 (no enabled rules) bootstrap=' . $bootstrap);
            $empty = yandex_yml_rules_write_combined_yml([], $siteBase, $bootstrap);
            if (!empty($empty['stale_kept'])) {
                $prevXml = @file_get_contents($snapPath);
                $keptOffers = is_string($prevXml) ? substr_count($prevXml, '<offer ') : 0;
                yandex_yml_rules_log_line('OK rules=0 stale_kept kept_offers≈' . $keptOffers);

                return [
                    'ok' => true,
                    'stale_kept' => true,
                    'rules_total' => 0,
                    'rules_ok' => 0,
                    'offers_written' => $keptOffers,
                    'offers_candidate' => 0,
                    'files' => [$snapPath],
                    'errors' => [],
                    'departure_feeds' => [],
                ];
            }
            if (empty($empty['ok'])) {
                return ['ok' => false, 'errors' => [$empty['error'] ?? 'write failed'], 'departure_feeds' => []];
            }
            yandex_yml_rules_log_line('OK rules=0 offers=0 files=' . implode(',', $empty['files'] ?? []));

            return [
                'ok' => true,
                'rules_total' => 0,
                'rules_ok' => 0,
                'offers_written' => (int) ($empty['offers'] ?? 0),
                'files' => $empty['files'] ?? [],
                'errors' => [],
                'departure_feeds' => [],
            ];
        }

        $proxyBase = get_tourvisor_proxy_http_base_url();
        $timeout = (int) (getenv('YML_RULES_HTTP_TIMEOUT') ?: ($_ENV['YML_RULES_HTTP_TIMEOUT'] ?? 120));
        $timeout = max(30, min(300, $timeout));
        $delaySec = (float) (getenv('YML_RULES_RULE_DELAY_SEC') ?: ($_ENV['YML_RULES_RULE_DELAY_SEC'] ?? 2));
        $delaySec = max(0.0, min(15.0, $delaySec));

        $datesFrom = date('Y-m-d', strtotime('+1 day'));
        $datesTo = date('Y-m-d', strtotime('+60 days'));
        $syncLive = filter_var(getenv('YML_RULES_SEARCH_LIVE') ?: ($_ENV['YML_RULES_SEARCH_LIVE'] ?? '0'), FILTER_VALIDATE_BOOLEAN);

        $allRows = [];
        /** @var array<int, list<array<string, mixed>>> $rowsByDeparture */
        $rowsByDeparture = [];
        $rulesOk = 0;

        foreach ($rules as $idx => $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $rid = (int) ($rule['id'] ?? 0);
            $depId = (int) ($rule['source_departure_id'] ?? 0);
            $depKey = th_departure_normalize_id($depId);
            $cityLabel = trim((string) ($rule['source_city'] ?? ''));
            $countryLabel = trim((string) ($rule['target_country'] ?? ''));

            $beforeErr = count($errors);
            $rows = yandex_yml_rules_build_rows_for_single_rule(
                $rule,
                $proxyBase,
                $timeout,
                $datesFrom,
                $datesTo,
                $syncLive,
                $siteBase,
                $imageProxyBase,
                $errors
            );
            if (count($errors) === $beforeErr && $depId > 0) {
                $rulesOk++;
            }

            foreach ($rows as $r) {
                $allRows[] = $r;
            }
            if ($depKey > 0) {
                if (!isset($rowsByDeparture[$depKey])) {
                    $rowsByDeparture[$depKey] = [];
                }
                foreach ($rows as $r) {
                    $rowsByDeparture[$depKey][] = $r;
                }
            }

            if ($idx < count($rules) - 1 && $delaySec > 0) {
                usleep((int) round($delaySec * 1_000_000));
            }
        }

        $departureFeeds = [];
        ksort($rowsByDeparture, SORT_NUMERIC);
        foreach ($rowsByDeparture as $depId => $depRows) {
            $mergedDep = yandex_yml_rules_merge_dedupe($depRows);
            $depSnap = yandex_yml_rules_feed_snapshot_path_for_departure($depId);
            $bootstrapDep = yandex_yml_rules_feed_snapshot_is_valid($depSnap) ? 0 : 1;
            $genDep = yandex_yml_rules_write_combined_yml_to($depSnap, $mergedDep, $siteBase, $bootstrapDep, []);
            $slug = yandex_yml_rules_departure_slug($depId);
            $slugPath = null;
            $slugStaleKept = false;
            if ($slug !== null && $slug !== '' && !empty($genDep['ok']) && empty($genDep['stale_kept'])) {
                $slugPub = yandex_yml_rules_publish_slug_snapshot_from_departure(
                    $depId,
                    $slug,
                    $depSnap,
                    (int) ($genDep['offers'] ?? 0)
                );
                $slugStaleKept = !empty($slugPub['stale_kept_slug']);
                if (!empty($slugPub['file']) && is_file((string) $slugPub['file'])) {
                    $slugPath = (string) $slugPub['file'];
                } elseif ($slugStaleKept) {
                    $slugPath = yandex_yml_rules_feed_snapshot_path_for_slug($slug);
                }
            }
            $entry = [
                'departure_id' => $depId,
                'slug' => $slug,
                'file' => $depSnap,
                'offers_written' => (int) ($genDep['offers'] ?? 0),
                'offers_candidate' => (int) ($genDep['offers'] ?? 0),
                'stale_kept' => !empty($genDep['stale_kept']),
                'stale_kept_slug' => $slugStaleKept,
                'ok' => !empty($genDep['ok']),
                'error' => empty($genDep['ok']) ? (string) ($genDep['error'] ?? '') : '',
            ];
            if ($slugPath !== null && is_file($slugPath)) {
                $entry['slug_file'] = $slugPath;
            }
            $departureFeeds[] = $entry;
            yandex_yml_rules_log_line('OK departure_feed dep=' . $depId . ' file=' . $depSnap . ' offers=' . ($genDep['offers'] ?? 0)
                . (!empty($genDep['stale_kept']) ? ' stale_kept=1' : ''));
        }

        $merged = yandex_yml_rules_merge_dedupe($allRows);
        $gen = yandex_yml_rules_write_combined_yml($merged, $siteBase, 0);
        if (!empty($gen['stale_kept'])) {
            $snapPath = yandex_yml_rules_feed_snapshot_path();
            $prevXml = @file_get_contents($snapPath);
            $keptOffers = is_string($prevXml) ? substr_count($prevXml, '<offer ') : 0;
            yandex_yml_rules_log_line('DONE stale_kept rules=' . count($rules) . ' ok=' . $rulesOk . ' offers_candidate=' . ($gen['offers'] ?? 0) . ' kept_offers≈' . $keptOffers);

            return [
                'ok' => true,
                'stale_kept' => true,
                'rules_total' => count($rules),
                'rules_ok' => $rulesOk,
                'offers_written' => $keptOffers,
                'offers_candidate' => (int) ($gen['offers'] ?? 0),
                'files' => [$snapPath],
                'errors' => $errors,
                'departure_feeds' => $departureFeeds,
            ];
        }
        if (empty($gen['ok'])) {
            $errors[] = (string) ($gen['error'] ?? 'YML write failed');
            yandex_yml_rules_log_line('FAIL write ' . ($gen['error'] ?? ''));

            return [
                'ok' => false,
                'rules_total' => count($rules),
                'rules_ok' => $rulesOk,
                'errors' => $errors,
                'departure_feeds' => $departureFeeds,
            ];
        }

        yandex_yml_rules_log_line('DONE rules=' . count($rules) . ' ok=' . $rulesOk . ' offers=' . ($gen['offers'] ?? 0) . ' files=' . implode(',', $gen['files'] ?? []));

        return [
            'ok' => true,
            'rules_total' => count($rules),
            'rules_ok' => $rulesOk,
            'offers_written' => (int) ($gen['offers'] ?? 0),
            'files' => $gen['files'] ?? [],
            'errors' => $errors,
            'departure_feeds' => $departureFeeds,
        ];
    } finally {
        yandex_yml_rules_release_lock($fh);
    }
}
