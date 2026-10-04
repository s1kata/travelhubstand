<?php
/**
 * Компонент для оптимизации изображений
 * Автоматически добавляет необходимые атрибуты для SEO и производительности
 */

/**
 * Генерирует оптимизированный тег img с SEO атрибутами
 * 
 * @param string $src Путь к изображению
 * @param string $alt Alt текст (обязательно для SEO)
 * @param array $options Дополнительные опции (width, height, loading, class, etc.)
 * @return string HTML тег img
 */
function optimizedImage($src, $alt, $options = []) {
    $width = $options['width'] ?? null;
    $height = $options['height'] ?? null;
    $loading = $options['loading'] ?? 'lazy';
    $class = $options['class'] ?? '';
    $sizes = $options['sizes'] ?? '100vw';
    $fetchpriority = $options['fetchpriority'] ?? null;
    $decoding = $options['decoding'] ?? 'async';
    
    // Определяем формат изображения
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $isWebP = $ext === 'webp';
    
    // Создаем атрибуты
    $attrs = [];
    $attrs[] = 'src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';
    $attrs[] = 'alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';
    
    if ($width) $attrs[] = 'width="' . (int)$width . '"';
    if ($height) $attrs[] = 'height="' . (int)$height . '"';
    if ($class) $attrs[] = 'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"';
    if ($loading) $attrs[] = 'loading="' . htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') . '"';
    if ($sizes) $attrs[] = 'sizes="' . htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8') . '"';
    if ($fetchpriority) $attrs[] = 'fetchpriority="' . htmlspecialchars($fetchpriority, ENT_QUOTES, 'UTF-8') . '"';
    if ($decoding) $attrs[] = 'decoding="' . htmlspecialchars($decoding, ENT_QUOTES, 'UTF-8') . '"';
    
    // Добавляем fallback для старых браузеров
    if (!$isWebP && isset($options['fallback'])) {
        $attrs[] = 'onerror="this.onerror=null;this.src=\'' . htmlspecialchars($options['fallback'], ENT_QUOTES, 'UTF-8') . '\'"';
    }
    
    return '<img ' . implode(' ', $attrs) . '>';
}

/**
 * Генерирует picture элемент с WebP и fallback
 * 
 * @param string $src Путь к изображению
 * @param string $alt Alt текст
 * @param array $options Дополнительные опции
 * @return string HTML тег picture
 */
function optimizedPicture($src, $alt, $options = []) {
    $webpSrc = $options['webp'] ?? str_replace(['.jpg', '.jpeg', '.png'], '.webp', $src);
    $class = $options['class'] ?? '';
    $width = $options['width'] ?? null;
    $height = $options['height'] ?? null;
    $loading = $options['loading'] ?? 'lazy';
    $sizes = $options['sizes'] ?? '100vw';
    
    $html = '<picture>';
    
    // WebP источник
    $html .= '<source srcset="' . htmlspecialchars($webpSrc, ENT_QUOTES, 'UTF-8') . '" type="image/webp">';
    
    // Оригинальный источник
    $attrs = [];
    if ($width) $attrs[] = 'width="' . (int)$width . '"';
    if ($height) $attrs[] = 'height="' . (int)$height . '"';
    if ($class) $attrs[] = 'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"';
    if ($loading) $attrs[] = 'loading="' . htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') . '"';
    if ($sizes) $attrs[] = 'sizes="' . htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8') . '"';
    
    $html .= '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" ' . implode(' ', $attrs) . '>';
    $html .= '</picture>';
    
    return $html;
}
