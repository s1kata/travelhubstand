<?php
/**
 * Универсальный SEO компонент для всех страниц
 * Использование: include __DIR__ . '/seo_head.php';
 * 
 * Переменные, которые должны быть установлены перед включением:
 * - $page_title - заголовок страницы (обязательно)
 * - $page_description - описание страницы (обязательно)
 * - $page_keywords - ключевые слова (опционально)
 * - $page_image - изображение для OG (опционально, по умолчанию используется логотип)
 * - $page_type - тип страницы для OG (article, website, product, place) (опционально, по умолчанию 'website')
 * - $canonical_url - канонический URL (опционально, по умолчанию текущий URL)
 * - $noindex - запретить индексацию (опционально, по умолчанию false)
 * - $schema_data - дополнительные данные для Schema.org (опционально)
 * - $already_has_title - если true, тег <title> не выводится (уже задан в шаблоне выше)
 */

// Базовый origin (HTTPS за reverse proxy: X-Forwarded-Proto / порт 443)
if (!function_exists('tourvisor_request_is_https')) {
    require_once __DIR__ . '/tourvisor_proxy_url.php';
}
$seo_proto = tourvisor_request_is_https() ? 'https' : 'http';
$seo_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$seo_base = rtrim($seo_proto . '://' . $seo_host, '/');

// Корень публичного сайта (не dirname(SCRIPT_NAME) — иначе /frontend/frontend/…)
$site_url = $seo_base . '/frontend';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($requestPath) || $requestPath === '') {
    $requestPath = '/';
}
$current_url = $seo_base . $requestPath;

// Значения по умолчанию
$page_title = $page_title ?? 'Travel Hub - Путешествия вашей мечты';
$page_description = $page_description ?? 'Travel Hub — туристическое агентство. Подбор туров, отелей, виз, страхования и трансферов. Путешествия по всему миру.';
$page_keywords = $page_keywords ?? 'туры, путешествия, отели, визы, турагентство, Travel Hub, отдых, туризм';
$page_image = $page_image ?? ($site_url . '/favicon.svg');
$page_type = $page_type ?? 'website';
$canonical_url = $canonical_url ?? $current_url;
$noindex = $noindex ?? false;
$schema_data = $schema_data ?? [];

// OG/Twitter: только абсолютный URL (иначе соцсети игнорируют картинку)
$page_image = (string) $page_image;
if ($page_image !== '' && !preg_match('#\Ahttps?://#i', $page_image)) {
    if ($page_image[0] === '/') {
        $page_image = $seo_proto . '://' . $seo_host . $page_image;
    } else {
        $page_image = $seo_proto . '://' . $seo_host . '/' . ltrim($page_image, '/');
    }
}

$already_has_title = $already_has_title ?? false;

// Очистка и экранирование
$page_title = htmlspecialchars(strip_tags($page_title), ENT_QUOTES, 'UTF-8');
$page_description = htmlspecialchars(strip_tags($page_description), ENT_QUOTES, 'UTF-8');

// Тег <title> — без него браузер показывает URL во вкладке
if (!$already_has_title && !isset($seo_head_title_printed)) {
    echo '<title>' . $page_title . '</title>' . "\n";
    $seo_head_title_printed = true;
}
$page_keywords = htmlspecialchars(strip_tags($page_keywords), ENT_QUOTES, 'UTF-8');
$page_image = htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8');
$canonical_url = htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8');

// Определяем язык страницы
$page_lang = $page_lang ?? 'ru';
?>
<!-- SEO Meta Tags -->
<meta name="description" content="<?php echo $page_description; ?>">
<meta name="keywords" content="<?php echo $page_keywords; ?>">
<meta name="author" content="Travel Hub">
<meta name="robots" content="<?php echo $noindex ? 'noindex, nofollow' : 'index, follow'; ?>">
<meta name="googlebot" content="<?php echo $noindex ? 'noindex, nofollow' : 'index, follow'; ?>">
<link rel="canonical" href="<?php echo $canonical_url; ?>">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="<?php echo $page_type; ?>">
<meta property="og:url" content="<?php echo $canonical_url; ?>">
<meta property="og:title" content="<?php echo $page_title; ?>">
<meta property="og:description" content="<?php echo $page_description; ?>">
<meta property="og:image" content="<?php echo $page_image; ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?php echo $page_title; ?>">
<meta property="og:site_name" content="Travel Hub">
<meta property="og:locale" content="<?php echo $page_lang === 'ru' ? 'ru_RU' : 'en_US'; ?>">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="<?php echo $canonical_url; ?>">
<meta name="twitter:title" content="<?php echo $page_title; ?>">
<meta name="twitter:description" content="<?php echo $page_description; ?>">
<meta name="twitter:image" content="<?php echo $page_image; ?>">
<meta name="twitter:site" content="@TravelHub">

<!-- Дополнительные meta теги -->
<meta name="theme-color" content="#5DA9A4">
<meta name="msapplication-TileColor" content="#5DA9A4">
<meta name="application-name" content="Travel Hub">
<meta name="apple-mobile-web-app-title" content="Travel Hub">

<!-- Schema.org JSON-LD -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "<?php echo $site_url; ?>/#organization",
      "name": "Travel Hub",
      "url": "<?php echo $site_url; ?>/",
      "logo": "<?php echo $site_url; ?>/favicon.svg",
      "description": "Туристическое агентство. Подбор туров, отелей, виз и трансферов по всему миру.",
      "email": "hello@travelhub63.ru",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Московское шоссе, 81Б, ТЦ «Парк Хаус»",
        "addressLocality": "Самара",
        "addressCountry": "RU"
      },
      "telephone": "+78462541656",
      "sameAs": [
        "https://t.me/TravelHub63",
        "https://vk.ru/hubtravel",
        "https://max.ru/u/f9LHodD0cOJpBbwh-zr3lqTmDxZiZMLDP-FuyTUa8fyzWO3S2tgc4_Mirnk"
      ],
      "contactPoint": {
        "@type": "ContactPoint",
        "email": "hello@travelhub63.ru",
        "contactType": "customer service",
        "areaServed": "RU",
        "availableLanguage": ["Russian", "English"]
      }
    },
    {
      "@type": "WebSite",
      "@id": "<?php echo $site_url; ?>/#website",
      "url": "<?php echo $site_url; ?>/",
      "name": "Travel Hub",
      "description": "Путешествия вашей мечты — туры, отели, визы.",
      "publisher": {
        "@id": "<?php echo $site_url; ?>/#organization"
      },
      "inLanguage": ["ru", "en"],
      "potentialAction": {
        "@type": "SearchAction",
        "target": {
          "@type": "EntryPoint",
          "urlTemplate": "<?php echo $site_url; ?>/index.php?search={search_term_string}"
        },
        "query-input": "required name=search_term_string"
      }
    },
    {
      "@type": "BreadcrumbList",
      "@id": "<?php echo $canonical_url; ?>#breadcrumb",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Главная",
          "item": "<?php echo $site_url; ?>/index.php"
        }<?php 
        // Добавляем хлебные крошки, если они переданы
        if (isset($breadcrumbs) && is_array($breadcrumbs)) {
            $pos = 2;
            foreach ($breadcrumbs as $crumb) {
                echo ",\n        {\n          \"@type\": \"ListItem\",\n          \"position\": $pos,\n          \"name\": \"" . htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') . "\",\n          \"item\": \"" . htmlspecialchars($crumb['url'] ?? '', ENT_QUOTES, 'UTF-8') . "\"\n        }";
                $pos++;
            }
        }
        ?>
      ]
    }<?php 
    // Добавляем дополнительные схемы, если они переданы.
    // Поддерживаем два режима:
    // 1) $schema_data — один объект (ассоц. массив)
    // 2) $schema_data — список объектов (indexed array), добавляем каждый как отдельный узел graph
    if (!empty($schema_data)) {
        $isList = is_array($schema_data)
            && array_keys($schema_data) === range(0, count($schema_data) - 1);

        if ($isList) {
            foreach ($schema_data as $item) {
                if (empty($item)) continue;
                echo ",\n    " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            }
        } else {
            echo ",\n    " . json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
    }
    ?>
  ]
}
</script>

<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="dns-prefetch" href="https://api.tourvisor.ru">
