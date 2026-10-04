<?php
/**
 * Генератор YML `export/services.yml` (акции + услуги) для отдачи через export/services_yml.php.
 *
 * Отдельный фид по правилам админки (страна, город вылета, лимит 20–25 туров): backend/scripts/yml_feed_rules_cron.php
 * → URL https://<SITE>/feed.yml (см. .htaccess и backend/components/yandex_yml_rules_runner.php).
 *
 * Запуск:
 * - Вручную: php backend/scripts/generate_yml.php
 * - После синка: php backend/scripts/sync_yandex_feed_offers.php
 * - Крон акций: см. .env.example (YML_FEED_*, совместно с promo_tours_refresh)
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/yandex_feed_schema.php';
require_once __DIR__ . '/../components/yandex_yml_rules_schema.php';
require_once __DIR__ . '/../components/yandex_feed_sync.php';

/** Применяет лимиты туров на страну из yandex_yml_feed_rules (после выборки из БД). */
function generate_yml_apply_rule_caps_to_promo_rows(array $promoRows, array $limitByCountry): array
{
    if ($limitByCountry === []) {
        return $promoRows;
    }
    $byC = [];
    foreach ($promoRows as $r) {
        $cid = (int) ($r['country_id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        if (!isset($byC[$cid])) {
            $byC[$cid] = [];
        }
        $byC[$cid][] = $r;
    }
    $out = [];
    foreach ($limitByCountry as $cid => $lim) {
        $cid = (int) $cid;
        $lim = max(1, min(500, (int) $lim));
        $chunk = $byC[$cid] ?? [];
        usort($chunk, static function (array $a, array $b): int {
            return ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0));
        });
        foreach (array_slice($chunk, 0, $lim) as $row) {
            $out[] = $row;
        }
    }
    usort($out, static function (array $a, array $b): int {
        $na = mb_strtolower((string) ($a['country_name'] ?? ''));
        $nb = mb_strtolower((string) ($b['country_name'] ?? ''));
        if ($na !== $nb) {
            return $na <=> $nb;
        }

        return ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0));
    });

    return $out;
}

/** Стабильный categoryId для страны (не пересекается с id=1 «Услуги»). */
function yml_category_id_for_country(int $countryId): int
{
    return 100000 + max(0, $countryId);
}

/**
 * Возвращает true, если URL уже абсолютный (http/https).
 */
function yml_is_absolute_url(string $url): bool
{
    return (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0);
}

/**
 * Генерирует YML и сохраняет в export/services.yml.
 *
 * @return array{ok: bool, file?: string, count?: int, error?: string, promo?: int, services?: int}
 */
function generate_services_yml(?PDO $pdo): array
{
    if (!$pdo) {
        error_log('[generate_yml] No database connection');
        return ['ok' => false, 'error' => 'No database connection'];
    }

    $baseUrl = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? 'https://travelhub.ru')), '/');
    if (preg_match('#/frontend/?$#i', $baseUrl)) {
        $baseUrl = (string) preg_replace('#/frontend/?$#i', '', $baseUrl);
        $baseUrl = rtrim($baseUrl, '/');
    }

    $shopName = (string) (getenv('YML_SHOP_NAME') ?: ($_ENV['YML_SHOP_NAME'] ?? 'Travel Hub'));
    $shopCompany = (string) (getenv('YML_SHOP_COMPANY') ?: ($_ENV['YML_SHOP_COMPANY'] ?? $shopName));

    try {
        yandex_feed_ensure_table($pdo);
    } catch (PDOException $e) {
        error_log('[generate_yml] yandex_feed_ensure_table: ' . $e->getMessage());
    }

    $promoRows = [];
    try {
        yandex_yml_rules_ensure_table($pdo);
        $policy = yandex_yml_feed_sync_policy($pdo);
        if (!$policy['legacy_map']) {
            $allowed = array_values(array_unique(array_filter(array_map(static fn ($x): int => (int) $x, $policy['country_ids']), static fn (int $x): bool => $x > 0)));
            if ($allowed === []) {
                $promoRows = [];
            } else {
                $placeholders = implode(',', array_fill(0, count($allowed), '?'));
                $stmt = $pdo->prepare('
                    SELECT id, tourvisor_tour_id, country_id, country_name, title, description, picture_url, price, offer_url
                    FROM yandex_feed_offers
                    WHERE enabled = 1 AND country_id IN (' . $placeholders . ')
                    ORDER BY country_name ASC, price ASC, id ASC
                ');
                $stmt->execute($allowed);
                $promoRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $promoRows = generate_yml_apply_rule_caps_to_promo_rows($promoRows, $policy['limit_by_country']);
            }
        } else {
            $stmt = $pdo->query('
            SELECT id, tourvisor_tour_id, country_id, country_name, title, description, picture_url, price, offer_url
            FROM yandex_feed_offers
            WHERE enabled = 1
            ORDER BY country_name ASC, price ASC, id ASC
        ');
            $promoRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (PDOException $e) {
        error_log('[generate_yml] promo query: ' . $e->getMessage());
        $promoRows = [];
    }

    $promoRowsDbCount = count($promoRows);
    error_log('[generate_yml] promo_offers_from_db_enabled=' . $promoRowsDbCount);

    $maxPromo = (int) (getenv('YML_FEED_MAX_OFFERS_TOTAL') ?: ($_ENV['YML_FEED_MAX_OFFERS_TOTAL'] ?? 500));
    $maxPromo = max(0, min(5000, $maxPromo));
    if ($maxPromo > 0 && count($promoRows) > $maxPromo) {
        $promoRows = array_slice($promoRows, 0, $maxPromo);
    }
    if ($maxPromo > 0 && $promoRowsDbCount > $maxPromo) {
        error_log('[generate_yml] promo_offers_after_total_cap=' . count($promoRows) . ' (cap=' . $maxPromo . ')');
    }

    try {
        $stmt = $pdo->query("
            SELECT id, name, price, description, url, available
            FROM services
            WHERE available = 1
            ORDER BY display_order ASC, name ASC
        ");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[generate_yml] DB query error, fallback services empty: ' . $e->getMessage());
        $services = [];
    }

    $exportDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'export';
    if (!is_dir($exportDir) && !@mkdir($exportDir, 0755, true) && !is_dir($exportDir)) {
        error_log('[generate_yml] Cannot create export dir: ' . $exportDir);
        return ['ok' => false, 'error' => 'Cannot create export dir'];
    }

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

    $writer->startElement('category');
    $writer->writeAttribute('id', '1');
    $writer->text('Услуги');
    $writer->endElement();

    $countryCategories = [];
    foreach ($promoRows as $row) {
        $cid = (int) ($row['country_id'] ?? 0);
        $catId = (string) yml_category_id_for_country(max(0, $cid));
        if (!isset($countryCategories[$catId])) {
            $label = trim((string) ($row['country_name'] ?? ''));
            if ($label === '') {
                $label = $cid > 0 ? ('Страна ' . $cid) : 'Акции';
            }
            $countryCategories[$catId] = $label;
        }
    }
    ksort($countryCategories, SORT_NATURAL);
    foreach ($countryCategories as $catId => $label) {
        $writer->startElement('category');
        $writer->writeAttribute('id', (string) $catId);
        $writer->text($label);
        $writer->endElement();
    }

    $writer->endElement();

    $writer->startElement('offers');
    $writtenPromo = 0;
    $writtenServices = 0;

    foreach ($promoRows as $row) {
        $dbId = (int) ($row['id'] ?? 0);
        $title = trim((string) ($row['title'] ?? ''));
        $priceFloat = (float) ($row['price'] ?? 0);
        if ($dbId <= 0 || $title === '' || $priceFloat <= 0) {
            continue;
        }

        $price = number_format($priceFloat, 2, '.', '');
        $descRaw = trim((string) ($row['description'] ?? ''));
        $desc = $descRaw !== '' ? mb_substr($descRaw, 0, 2000) : mb_substr($title, 0, 500);

        $url = trim((string) ($row['offer_url'] ?? ''));
        if ($url === '') {
            continue;
        }
        if (!yml_is_absolute_url($url)) {
            $url = $baseUrl . '/' . ltrim($url, '/');
        }

        $pic = trim((string) ($row['picture_url'] ?? ''));
        if ($pic !== '' && !yml_is_absolute_url($pic)) {
            $pic = $baseUrl . '/' . ltrim($pic, '/');
        }
        if (!yandex_feed_is_valid_tour_picture($pic)) {
            continue;
        }

        $countryId = (int) ($row['country_id'] ?? 0);
        $categoryId = (string) yml_category_id_for_country(max(0, $countryId));

        $offerId = 'promo-' . $dbId;

        $writer->startElement('offer');
        $writer->writeAttribute('id', $offerId);
        $writer->writeAttribute('available', 'true');

        $writer->writeElement('url', $url);
        $writer->writeElement('price', $price);
        $writer->writeElement('currencyId', 'RUB');
        $writer->writeElement('categoryId', $categoryId);
        $writer->writeElement('name', mb_substr($title, 0, 500));
        $writer->writeElement('description', $desc);
        $writer->writeElement('picture', $pic);

        $writer->endElement();
        $writtenPromo++;
    }

    foreach ($services as $service) {
        $id = (int) ($service['id'] ?? 0);
        $name = trim((string) ($service['name'] ?? ''));
        $priceFloat = (float) ($service['price'] ?? 0);
        if ($id <= 0 || $name === '' || $priceFloat <= 0) {
            continue;
        }

        $price = number_format($priceFloat, 2, '.', '');
        $descRaw = trim((string) ($service['description'] ?? ''));
        $desc = $descRaw !== '' ? mb_substr($descRaw, 0, 1000) : $name;

        $urlRaw = trim((string) ($service['url'] ?? ''));
        if ($urlRaw === '') {
            $url = $baseUrl . '/frontend/window/services.php';
        } else {
            $url = yml_is_absolute_url($urlRaw) ? $urlRaw : ($baseUrl . '/' . ltrim($urlRaw, '/'));
        }

        $picDefault = $baseUrl . '/frontend/favicon.svg';

        $writer->startElement('offer');
        $writer->writeAttribute('id', (string) $id);
        $writer->writeAttribute('available', 'true');

        $writer->writeElement('url', $url);
        $writer->writeElement('price', $price);
        $writer->writeElement('currencyId', 'RUB');
        $writer->writeElement('categoryId', '1');
        $writer->writeElement('name', $name);
        $writer->writeElement('description', $desc);
        $writer->writeElement('picture', $picDefault);

        $writer->endElement();
        $writtenServices++;
    }

    $writer->endElement();
    $writer->endElement();
    $writer->endElement();
    $writer->endDocument();

    $filePath = $exportDir . DIRECTORY_SEPARATOR . 'services.yml';
    $bytes = @file_put_contents($filePath, $writer->outputMemory());
    if ($bytes === false) {
        error_log('[generate_yml] Cannot write file: ' . $filePath);
        return ['ok' => false, 'error' => 'Cannot write YML file'];
    }

    $total = $writtenPromo + $writtenServices;

    error_log(sprintf(
        '[generate_yml] yml_offers_written promo=%d services=%d total=%d (promo_rows_input=%d)',
        $writtenPromo,
        $writtenServices,
        $total,
        count($promoRows)
    ));

    return [
        'ok' => true,
        'file' => $filePath,
        'count' => $total,
        'promo' => $writtenPromo,
        'services' => $writtenServices,
    ];
}

$isDirectRun = isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__;
if ($isDirectRun) {
    $result = generate_services_yml($pdo);
    if (!$result['ok']) {
        if (php_sapi_name() === 'cli') {
            echo 'YML generation failed: ' . ($result['error'] ?? 'unknown') . "\n";
        }
        exit(1);
    }

    if (php_sapi_name() === 'cli') {
        $p = (int) ($result['promo'] ?? 0);
        $s = (int) ($result['services'] ?? 0);
        echo 'YML: ' . ($result['file'] ?? '') . " (promo {$p}, services {$s}, total " . ($result['count'] ?? 0) . ")\n";
    }
}
